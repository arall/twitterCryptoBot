<?php

namespace App\Libs\Clients;

use Exception;
use Illuminate\Support\Facades\Http;
use Spatie\TwitterStreamingApi\PublicStream;

/**
 * Twitter API Client.
 */
class Twitter
{
    /**
     * API Bearer Token.
     *
     * @var string
     */
    private $bearerToken;

    /**
     * Access Key.
     *
     * @var string
     */
    private $accessKey;

    /**
     * Access Secret.
     *
     * @var string
     */
    private $accessSecret;

    /**
     * Consumer Key.
     *
     * @var string
     */
    private $consumerKey;

    /**
     * Consumer Secret.
     *
     * @var string
     */
    private $consumerSecret;

    /**
     * API URL.
     */
    const API = 'https://api.twitter.com/';

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

        $this->generateBearerToken();
    }

    /**
     * Obtain a bearer token.
     */
    private function generateBearerToken()
    {
        $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->asForm()
            ->post(self::API . 'oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $this->bearerToken = $response->json()['access_token'];
    }

    /**
     * Check the APIs.
     *
     * @return bool
     */
    public function checkAPI()
    {
        $response = $this->request('users/1101060631950696448/tweets');
        if (!isset($response['data'])) {
            return false;
        }

        // TODO: Check Access Tokens

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
        $paginationToken = null;
        $tweets = [];

        do {
            $current++;

            $response = $this->request('users/' . $userId . '/tweets', [
                'tweet.fields' => 'created_at',
                'max_results' => 100,
                'pagination_token' => $paginationToken,
            ]);

            $paginationToken = $response['meta']['next_token'];

            foreach ($response['data'] as $tweet) {
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
        $response = $this->request('users/by', [
            'usernames' => $username,
        ]);

        return $response['data'][0]['id'];
    }

    /**
     * Listen for Tweets.
     *
     * @param string $username
     * @param  $function
     */
    public function listen($username, callable $function)
    {
        $userId = $this->getUserId($username);

        $stream = PublicStream::create(
            $this->accessKey,
            $this->accessSecret,
            $this->consumerKey,
            $this->consumerSecret,
        );

        $stream->whenTweets($userId, $function)
            ->startListening();
    }

    /**
     * Perform an API request.
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    private function request($endpoint, $data = [])
    {
        $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->bearerToken])
            ->get(self::API . '2/' . $endpoint, $data);

        $json = $response->json();

        if (isset($json['errors'])) {
            throw new Exception($json['errors']);
        }

        return $json;
    }
}
