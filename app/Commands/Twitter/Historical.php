<?php

namespace App\Commands\Twitter;

use App\Libs\Clients\Twitter;
use App\Libs\Analyzers\Binance;
use App\Libs\Processors\Twitter\CryptoEliz;
use LaravelZero\Framework\Commands\Command;
use Exception;

class Historical extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'twitter:historical {twitter} {processor} {entry=avg : The entry price of the first candle. High, Low or AVG} {--limit=500} {--csv=}';

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
    private $twitter;

    /**
     * Binance Analyzer
     *
     * @var App\Libs\Analyzers\Binance
     */
    private $analyzer;

    /**
     * Twitter user
     *
     * @var string
     */
    private $twitterUser;

    /**
     * Processor
     *
     * @var APp\Libs\Contracts\Interfaces\Processor
     */
    private $processor;

    /**
     * Tweet limit
     *
     * @var int
     */
    private $limit;

    /**
     * Entry strategy.
     *
     * @var string
     */
    private $entryStrategy;

    /**
     * CSV output path.
     *
     * @var string
     */
    private $csvPath;

    /**
     * Results
     *
     * @var array
     */
    private $results = [];

    /**
     * Initialization.
     */
    public function init()
    {
        $this->csvPath = $this->option('csv');
        $this->limit = $this->option('limit');

        $this->entryStrategy = strtolower($this->argument('entry'));
        if (!in_array($this->entryStrategy, ['avg', 'high', 'low'])) {
            throw new Exception('Invalid argument for entry. Use AVG, High or Low');
        }

        $this->twitterUser = $this->argument('twitter');
        $processor = 'App\\Libs\\Processors\\Twitter\\' . $this->argument('processor');
        if (!class_exists($processor)) {
            throw new Exception('Twitter processor not found');
        }
        $this->processor = $processor;
        $this->twitter = new Twitter();
        $this->analyzer = new Binance();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->init();

        $signals = $this->obtainSignals();

        $this->analyzeSignals($signals);

        $this->showResults();

        if ($this->csvPath) {
            $this->storeCSV();
        }
    }

    /**
     * Obtain signals from Twitter.
     *
     * @return array
     */
    private function obtainSignals()
    {
        $this->info('Obtaining last ' . $this->limit . ' Tweets...');

        $feed = $this->twitter->historical($this->twitterUser, $this->limit);

        $signals = [];
        foreach ($feed as $tweet) {
            $data = $this->processor::parse($tweet['text']);
            if (!$data) {
                continue;
            }

            $signals[] = [
                'symbol' => $data['symbol'],
                'date' => $tweet['created_at'],
            ];
        }

        $this->comment(count($signals) . ' Signals extracted');

        return $signals;
    }

    /**
     * Analyze signals.
     *
     * @param array $signals
     */
    private function analyzeSignals($signals)
    {
        $this->info('Analyzing signals...');

        $bar = $this->output->createProgressBar(count($signals));
        $bar->start();

        foreach ($signals as $signal) {
            $bar->advance();

            $this->results[] = $this->analyzer->analyze($signal['symbol'], $signal['date']);
        }

        $bar->finish();
    }

    /**
     * Display the results.
     */
    private function showResults()
    {
        $this->newLine();
        $this->newLine();

        $this->table(
            array_keys($this->results[0]),
            $this->results,
        );

        $this->newLine();
        $this->newLine();

        $totalTP = 0;
        $totalSL = 0;
        $profit = 0;
        $loss = 0;
        $total = 0;

        foreach ($this->results as $result) {
            // Ignore on going trades
            if (!isset($result['result'])) {
                continue;
            }

            $total++;

            if ($result['result'] == 'SL') {
                $totalSL++;
                $profit += env('TAKE_PROFIT');
            } elseif ($result['result'] == 'TP') {
                $totalTP++;
                $loss += env('STOP_LOSS');
            }
        }

        $this->info('Totals:');
        $this->comment('TP: ' . $totalTP . ' (' . round($totalTP / $total * 100) . ' %)');
        $this->comment('SL: ' . $totalSL . ' (' . round($totalSL  / $total * 100) . ' %)');
        $this->comment('Profit: ' . $profit . ' %');
        $this->comment('Loss: ' . $loss . ' %');
        $this->comment('Net Profit: ' . ($profit - $loss) . ' %');
    }

    /**
     * Store results to CSV.
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
        fputcsv($fp, array_keys($this->results[0]), ';');
        foreach ($this->results as $result) {
            fputcsv($fp, $result, ';');
        }
        fclose($fp);

        $this->newLine();
        $this->newLine();

        $this->info('CSV stored at ' . $this->csvPath);
    }
}
