<?php

namespace App\Commands;

use App\Libs\Clients\Twitter;
use App\Libs\Clients\Binance;
use App\Libs\Processors\CryptoEliz;
use LaravelZero\Framework\Commands\Command;
use DateTime;
use Exception;

class Historical extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'historical {--csv=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Perform a check on historical data';

    /**
     * Twitter Client
     *
     * @var App\Libs\Clients\Twitter
     */
    private $twitterClient;

    /**
     * Binance Client
     *
     * @var App\Libs\Clients\Binance
     */
    private $binanceClient;

    /**
     * Entries
     *
     * @var array
     */
    private $entries = [];

    /**
     * CSV output path.
     *
     * @var string
     */
    private $csvPath;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->twitterClient = new Twitter();
        $this->binanceClient = new Binance();

        $this->csvPath = $this->option('csv');

        $this->info('Obtaining past Tweets...');
        $feed = $this->twitterClient->historical(500);
        $this->comment(count($feed) . ' Tweets obtained');

        $this->info('Analyzing signals...');
        $bar = $this->output->createProgressBar(count($feed));
        $bar->start();

        foreach ($feed as $tweet) {
            $bar->advance();

            $data = CryptoEliz::parse($tweet['text']);
            if (!$data) {
                continue;
            }

            $this->analyze($data['symbol'], $tweet['created_at']);
        }

        $bar->finish();

        $this->showOutput();

        if ($this->csvPath) {
            $this->storeCSV();
        }
    }

    /**
     * Analyze the signal.
     *
     * @param  string $symbol
     * @param  string $dateTime
     * @return array
     */
    public function analyze($symbol, $dateTime)
    {
        $date = new DateTime($dateTime);

        $symbolBinance = $symbol . env('BINANCE_FIAT');

        // New Tweet
        $dateStart = strtotime($dateTime) * 1000;
        $klines = $this->binanceClient->klines($symbolBinance, env('BINANCE_INTERVAL'), $dateStart, null, '1');

        // AVG entry
        // $entry = ($klines[0][2] + $klines[0][3]) / 2;
        // Worst entry (highest price)
        $entry = $klines[0][2];

        // Exits
        $takeProfit = $entry * env('TAKE_PROFIT');
        $stopLoss = $entry - (($entry * env('STOP_LOSS')) - $entry);

        $entry = [
            'symbol' => $symbol,
            'date' => $date->format('Y-m-d H:i:s'),
            'open' => $klines[0][1],
            'high' => $klines[0][2],
            'low' => $klines[0][3],
            'close' => $klines[0][4],
            'entry' => $entry,
            'take_profit' => $takeProfit,
            'stop_loss' => $stopLoss,
        ];

        // Simulate next 1k intervals
        $klines = $this->binanceClient->klines($symbolBinance, env('BINANCE_INTERVAL'), $dateStart, null, '1000');
        foreach ($klines as $i => $kline) {

            $entry['intervals'] = $i;

            // Stop loss reached?
            if ($kline[3] <= $stopLoss) {
                $entry['result'] = 'SL';
                break;
            }

            // Take Profit reached?
            if ($kline[2] >= $takeProfit) {
                $entry['result'] = 'TP';
                break;
            }
        }

        $this->entries[] = $entry;

        return $entry;
    }

    /**
     * Display the output.
     */
    private function showOutput()
    {
        $this->newLine();
        $this->newLine();

        $this->table(
            array_keys($this->entries[0]),
            $this->entries,
        );

        $this->newLine();
        $this->newLine();

        $totalTP = 0;
        $totalSL = 0;
        $profit = 0;
        $loss = 0;
        $total = count($this->entries);
        foreach ($this->entries as $entry) {
            if ($entry['result'] == 'SL') {
                $totalSL++;
                $profit += env('TAKE_PROFIT');
            } elseif ($entry['result'] == 'TP') {
                $totalTP++;
                $loss += env('STOP_LOSS');
            }
        }

        $this->info('Totals:');
        $this->comment('TP: ' . $totalTP . ' (' . round($total / $totalTP * 100) . ' %)');
        $this->comment('SL: ' . $totalSL . ' (' . round($total / $totalSL * 100) . ' %)');
        $this->comment('Profit: ' . $profit . ' %');
        $this->comment('Loss: ' . $loss . ' %');
        $this->comment('Net Profit: ' . ($profit - $loss) . ' %');
    }

    /**
     * Store entries to CSV.
     */
    private function storeCSV()
    {
        if (!is_writable(dirname($this->csvPath))) {
            throw new Exception('Directory ' . $this->csvPath . ' does not exists.');
        }

        if (!is_writable(dirname($this->csvPath))) {
            throw new Exception('Directory ' . $this->csvPath . ' is not writable.');
        }

        $fp = fopen($this->csvPath, 'w');
        fputcsv($fp, array_keys($this->entries[0]), ';');
        foreach ($this->entries as $entry) {
            fputcsv($fp, $entry, ';');
        }
        fclose($fp);

        $this->info('CSV stored at ' . $this->csvPath);
    }
}
