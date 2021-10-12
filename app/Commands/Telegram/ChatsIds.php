<?php

namespace App\Commands\Telegram;

use App\Libs\Clients\Telegram;
use LaravelZero\Framework\Commands\Command;

class ChatsIds extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'telegram:chatsids';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Get all Telegram Chats Ids';

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

        $chats = $telegram->getChats();

        print_r($chats);
        exit;

        foreach ($chats as $chat) {
            $this->outout('[' . $chat[''] . '] - ' . $chat['']);
        }
    }
}
