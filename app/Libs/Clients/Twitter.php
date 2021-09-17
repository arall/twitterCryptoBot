<?php

namespace App\Libs\Clients;

use Illuminate\Support\Facades\Http;

class Twitter
{
    private $bearerToken;

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
    }

    /**
     * Check the APIs.
     *
     * @return bool
     */
    public function checkAPI()
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->bearerToken])
            ->get(self::API . 'users/' . $this->userId . '/tweets', [
                'tweet.fields' => 'created_at',
                'max_results' => 1,
            ]);

        if ($response->failed()) {
            return false;
        }

        return true;
    }

    /**
     * Get the user historical feed.
     *
     * @param string $username
     * @param integer $limit
     * @return array
     */
    public function historical(string $username, $limit = 100)
    {
        $userId = $this->getUserId($username);

        $current = 0;
        $max = $limit / 100;
        $token = null;
        $tweets = [];

        do {
            $current++;

            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->bearerToken])
                ->get(self::API . 'users/' . $userId . '/tweets', [
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

    /**
     * Get User ID by username.
     *
     * @param string $username
     * @return int
     */
    public function getUserId($username)
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->bearerToken])
            ->get(self::API . 'users/by', [
                'usernames' => $username,
            ]);

        return $response->json()['data'][0]['id'];
    }
}
