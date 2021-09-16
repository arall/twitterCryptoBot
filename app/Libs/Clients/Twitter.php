<?php

namespace App\Libs\Clients;

use Illuminate\Support\Facades\Http;

class Twitter
{
    private $bearerToken;
    private $userId;

    const API = 'https://api.twitter.com/2/';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->bearerToken = env('TWITTER_BEARER_TOKEN');
        $this->accessKey = env('TWITTER_ACCESS_TOKEN');
        $this->accessSecret = env('TWITTER_ACCESS_SECRET');
        $this->consumerKey = env('TWITTER_CONSUMER_KEY');
        $this->consumerSecret = env('TWITTER_CONSUMER_SECRET');
        $this->userId = env('TWITTER_USER_ID');
    }

    /**
     * Get the user historical feed.
     *
     * @param integer $limit
     * @return array
     */
    public function historical($limit = 100)
    {
        $current = 0;
        $max = $limit / 100;
        $token = null;
        $tweets = [];

        do {
            $current++;

            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->bearerToken])
                ->get(self::API . 'users/' . $this->userId . '/tweets', [
                    'tweet.fields' => 'created_at',
                    'max_results' => 100,
                    'pagination_token' => $token,
                ]);

            $json = $response->json();

            $token = $json['meta']['next_token'];

            foreach ($json['data'] as $tweet) {
                $tweets[] = $tweet;
            }
        } while ($current < $max);

        return $tweets;
    }
}
