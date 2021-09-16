<?php

namespace App\Libs\Clients;

use Illuminate\Support\Facades\Http;

class Binance
{
    private $key;
    private $secret;

    const API = 'https://api.binance.com/api/v3/';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->key = env('BINANCE_API_KEY');
        $this->secret = env('BINANCE_API_SECRET');
    }

    /**
     * Check if API is working.
     *
     * @return bool
     */
    public function checkAPI()
    {
        $response = $this->request('account');

        return isset($response['balances']);
    }

    /**
     * Check symbol price at specific time.
     *
     * @param  string $symbol
     * @param  string $interval (1m / 1h / 1d...)
     * @param  int    $startTime (milliseconds)
     * @param  int    $endTime (milliseconds)
     * @param  int    $limit (max 1000)
     * @return array
     */
    public function klines($symbol, $interval, $startTime = null, $endTime = null, $limit = 500)
    {
        // 0  - Open time
        // 1  - Open
        // 2  - High
        // 3  - Low
        // 4  - Close
        // 5  - Volume
        // 6  - Close time
        // 7  - Quote asset volume
        // 8  - Number of trades
        // 9  - Taker buy base asset volume
        // 10 - Taker buy quote asset volume

        return $this->request('klines', [
            'symbol' => $symbol,
            'interval' => $interval,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit,
        ]);
    }

    /**
     * Perform an API request.
     *
     * @param  string $endpoint
     * @param  array  $params
     * @return array
     */
    private function request($endpoint, $params = [])
    {
        if ($endpoint == 'user') {
            $params['timestamp'] = number_format(microtime(true) * 1000, 0, '.', '');
            $params['signature'] = hash_hmac('sha256', http_build_query($params, '', '&'), $this->secret);
        }

        // echo '[-] Binance API: ' . $endpoint . ' - ' .  http_build_query($params) . PHP_EOL;

        $response = Http::withHeaders(['X-MBX-APIKEY' => $this->key])->get(self::API . $endpoint, $params);

        return $response->json();
    }
}
