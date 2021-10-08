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
     * Binance Client
     *
     * @var App\Libs\Clients\Binance
     */
    private $binance;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->binance = new Binance();

        $symbol = 'XMR';
        $pair = strtoupper($symbol . getenv('BINANCE_PAIR'));

        $response = $this->binance->buy($pair, getenv('BINANCE_AMOUNT_PER_TRADE'));
        $quantity = $response['executedQty'];

        $avgEntryPrice = 0;
        foreach ($response['fills'] as $fill) {
            $avgEntryPrice += $fill['price'];
        }
        $avgEntryPrice = $avgEntryPrice / count($response['fills']);

        $this->comment('Bought ' . $quantity . ' ' . $symbol . ' at ' . $avgEntryPrice . ' ' . getenv('BINANCE_PAIR'));

        // Take Profit & Stop Loss order
        $takeProfitPrice = $avgEntryPrice * getenv('TAKE_PROFIT');
        $stopLossPrice = $avgEntryPrice - (($avgEntryPrice * getenv('STOP_LOSS')) - $avgEntryPrice);

        $this->info('Take Profit Price: ' . $takeProfitPrice . ' ' . $symbol);
        $this->info('Stop Loss Price: ' . $stopLossPrice . ' ' . $symbol);

        $trade = $this->binance->sellWithStopLoss($pair, $quantity, $takeProfitPrice, $stopLossPrice);

        $this->comment('Trade created!');
    }
}
