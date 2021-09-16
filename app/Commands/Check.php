<?php

namespace App\Commands;

use App\Libs\Clients\Binance;
use LaravelZero\Framework\Commands\Command;

class Check extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'check';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Check APIs';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Binance();

        // $client->checkAPI();

        $symbol = 'BATUSDT';
        $interval = '1m';

        // New Tweet
        $dateStart = strtotime('2021-09-16 14:29:45') * 1000;
        $klines = $client->klines($symbol, $interval, $dateStart, null, '1');

        // AVG entry
        // $entry = ($kline[0][2] + $kline[0][3]) / 2;

        // Worst entry (highest price)
        $entry = $klines[0][2];

        // Exits
        $takeProfit = $entry * env('TAKE_PROFIT');
        $stopLoss = $entry - (($entry * env('STOP_LOSS')) - $entry);

        $this->info('Symbol: ' . $symbol);
        $this->info('Interval: ' . $interval);
        $this->info('Entry: ' . $entry);
        $this->info('Take Profit: ' . $takeProfit);
        $this->info('Stop Loss: ' . $stopLoss);
        $this->line('Analyzing...');

        // Simulate next 1k minutes
        $klines = $client->klines($symbol, $interval, $dateStart, null, '1000');
        foreach ($klines as $i => $kline) {

            // Stop loss reached?
            if ($kline[3] <= $stopLoss) {
                $this->comment('Stop loss reached after ' . $i . ' minutes');
                break;
            }

            // Take Profit reached?
            if ($kline[2] >= $takeProfit) {
                $this->comment('Take profit reached after ' . $i . ' minutes');
                break;
            }
        }
    }
}
