<?php

namespace App\Commands\Test;

use App\Libs\Clients\Binance;
use LaravelZero\Framework\Commands\Command;

class Trade extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'test:trade';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Test Binance Trade';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $binance = new Binance();

        // Buy BTC with USDT
        $response = $binance->buy('OMGBUSD', getenv('BINANCE_AMOUNT_PER_TRADE'));

        $quantity = $response['executedQty'];

        $avgEntryPrice = 0;
        foreach ($response['fills'] as $fill) {
            $avgEntryPrice += $fill['price'];
        }
        $avgEntryPrice = $avgEntryPrice / count($response['fills']);

        $this->info('AVG Entry Price: ' . $avgEntryPrice);

        // Take Profit & Stop Loss order
        $takeProfitPrice = $avgEntryPrice * getenv('TAKE_PROFIT');
        $stopLossPrice = $avgEntryPrice - (($avgEntryPrice * getenv('STOP_LOSS')) - $avgEntryPrice);

        $this->info('Take Profit Price: ' . $takeProfitPrice);
        $this->info('Stop Loss Price: ' . $stopLossPrice);

        $binance->sellWithStopLoss('BTCUSDT', $quantity, $takeProfitPrice, $stopLossPrice);
    }
}
