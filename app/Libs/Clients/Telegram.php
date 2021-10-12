<?php

namespace App\Libs\Clients;

use \danog\MadelineProto\API;

/**
 * Telegram API Client.
 */
class Telegram
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // @todo Replace with https://docs.madelineproto.xyz/docs/LOGIN.html#api-id
        $this->client = new API('session.madeline');
        $this->client->start();
    }

    /**
     * Logout.
     */
    public function logout()
    {
        $this->client->logout();
    }

    /**
     * Get all the chats.
     *
     * @return array
     */
    public function getChats()
    {
        $dialogs = $this->client->getFullDialogs();
        foreach ($dialogs as $peer) {
            dd($peer);
            if (!isset($peer['channel_id'])) {
                continue;
            }

            $result = $this->client->getInfo($peer['channel_id']);

            echo ' ID: ' . $peer['channel_id'] . PHP_EOL;
            print_r($result);
            exit;
        }
    }

    /**
     * Get the channel historical messages.
     *
     * @param string $channel
     * @param integer $limit
     * @return array
     */
    public function historical(string $channel, $limit = 100)
    {
        $messages = [];

        $page = 0;
        $offset_id = 0;
        $limitPerPage = 100;
        if ($limitPerPage > $limit) {
            $limitPerPage = $limit;
        }

        do {
            $page++;
            $messages_Messages = $this->client->messages->getHistory(
                [
                    'peer' => $channel,
                    'offset_id' => $offset_id,
                    'offset_date' => 0,
                    'add_offset' => 0,
                    'limit' => $limitPerPage,
                    'max_id' => 0,
                    'min_id' => 0,
                    'hash' => 0
                ]
            );

            if (count($messages_Messages['messages']) == 0) break;

            foreach ($messages_Messages['messages'] as $message) {
                if (!isset($message['message'])) {
                    continue;
                }
                $messages[] = [
                    'text' => $message['message'],
                    'created_at' => date('Y-m-d H:i:s', $message['date']),
                ];
            }

            $offset_id = end($messages_Messages['messages'])['id'];
            if ($page * $limitPerPage >= $limit) {
                break;
            }
        } while (true);

        return $messages;
    }
}
