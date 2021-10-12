<?php

namespace App\Commands\Telegram;

use App\Libs\Clients\Telegram;
use LaravelZero\Framework\Commands\Command;

class Logout extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'telegram:logout';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Logout from Telegram Proto';

    /**
     * Execute the console command.
     *
     * @todo
     *
     * @return mixed
     */
    public function handle()
    {
        $telegram = new Telegram();
        $telegram->logout();
    }
}
