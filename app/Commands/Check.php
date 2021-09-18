<?php

namespace App\Commands;

use App\Libs\Clients\Binance;
use App\Libs\Clients\Twitter;
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
        $binance = new Binance();
        $twitter = new Twitter();

        if (!$binance->checkAPI()) {
            $this->error('Binance API failed.');
        } else {
            $this->info('Binance API OK');
            $this->comment('Binance Available Balance in ' . getenv('BINANCE_PAIR') . ': ' . $binance->getPairBalance());
        }

        if (!$twitter->checkAPI()) {
            $this->error('Twitter API failed.');
        } else {
            $this->info('Twitter API OK');
        }
    }
}
