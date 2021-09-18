<?php

namespace App\Libs\Clients;

use Illuminate\Support\Facades\Http;

/**
 * Binance API Client.
 */
class Binance
{
    /**
     * API key.
     *
     * @var string
     */
    private $key;

    /**
     * API secret.
     *
     * @var string
     */
    private $secret;

    /**
     * Use Testnet.
     *
     * @var bool
     */
    private $test = false;

    /**
     * Binance API URL.
     */
    const API = 'https://api.binance.com/api/v3/';

    /**
     * Binance Test API URL.
     */
    const TEST_API = 'https://testnet.binance.vision/api/v3/';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->key = env('BINANCE_API_KEY');
        $this->secret = env('BINANCE_API_SECRET');
        $this->test = env('BINANCE_TEST');
    }

    /**
     * Get the API URL to use.
     *
     * @return string
     */
    private function getApiUrl()
    {
        return $this->test ? self::TEST_API : self::API;
    }

    /**
     * Check if API is working.
     *
     * Will check if the API key allows SPOT operations.
     *
     * @return bool
     */
    public function checkAPI()
    {
        $response = $this->request('account', [
            'timestamp' => round(microtime(true) * 1000),
        ]);

        return in_array('SPOT', $response['permissions']);
    }

    /**
     * Check Pair balance.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#account-information-user_data
     *
     * @return float
     */
    public function getPairBalance()
    {
        $response = $this->request('account', [
            'timestamp' => round(microtime(true) * 1000),
        ]);

        foreach ($response['balances'] as $balance) {
            if ($balance['asset'] == getenv('BINANCE_PAIR')) {
                return $balance['free'];
            }
        }

        return 0;
    }

    /**
     * Check symbol candles at specific time.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#kline-candlestick-data
     *
     * @param  string $symbol
     * @param  string $interval (1m / 1h / 1d...)
     * @param  int    $startTime (milliseconds)
     * @param  int    $endTime (milliseconds)
     * @param  int    $limit (max 1000)
     * @return array
     *      0  - Open time
     *      1  - Open
     *      2  - High
     *      3  - Low
     *      4  - Close
     *      5  - Volume
     *      6  - Close time
     *      7  - Quote asset volume
     *      8  - Number of trades
     *      9  - Taker buy base asset volume
     *      10 - Taker buy quote asset volume
     */
    public function klines($symbol, $interval, $startTime = null, $endTime = null, $limit = 500)
    {
        return $this->request('klines', [
            'symbol' => $symbol,
            'interval' => $interval,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit,
        ]);
    }

    /**
     * Set a trade.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#new-order-trade
     *
     * @param string $symbol
     * @return array
     */
    public function trade($symbol)
    {
        $response = $this->request('order', [
            'symbol' => $symbol,
            'side' => '',
            'type' => 'MARKET', # LIMIT, MARKET
            'quantity' => '',
        ]);

        dd($response);
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
        if ($this->endpointRequiresAuth($endpoint)) {
            $params['timestamp'] = number_format(microtime(true) * 1000, 0, '.', '');
            $params['signature'] = hash_hmac('sha256', http_build_query($params, '', '&'), $this->secret);
        }

        $response = Http::withHeaders(['X-MBX-APIKEY' => $this->key])
            ->get($this->getApiUrl() . $endpoint, $params);

        return $response->json();
    }

    /**
     * Check if the endpoint requires authentication headers.
     *
     * @param string $endpoint
     * @return bool
     */
    private function endpointRequiresAuth($endpoint)
    {
        return in_array($endpoint, [
            'account',
            'order',
        ]);
    }
}
