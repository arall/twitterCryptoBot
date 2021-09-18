<?php

namespace App\Commands;

use App\Libs\Clients\Binance;
use LaravelZero\Framework\Commands\Command;

class TradeTest extends Command
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
        $binance->trade('BTCUSDT');
    }
}
