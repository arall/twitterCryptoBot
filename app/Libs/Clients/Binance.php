<?php

namespace App\Libs\Clients;

use Exception;
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
     * Exchange info.
     *
     * @var array
     */
    private $exchangeInfo;

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

        $this->loadExchangeInfo();
    }

    /**
     * Load the exchange info.
     *
     * This contains price filters and other specifications needed.
     */
    private function loadExchangeInfo()
    {
        $response = Http::get('https://api.binance.com/api/v1/exchangeInfo');

        $this->exchangeInfo = $response->json();
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
        $response = $this->request('GET', 'account', [
            'timestamp' => round(microtime(true) * 1000),
        ]);

        return in_array('SPOT', $response['permissions']);
    }

    /**
     * Check Pair balance.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#account-information-user_data
     *
     * @param string $asset (BTC)
     * @return float
     */
    public function getBalance($asset)
    {
        $asset = strtoupper($asset);

        $response = $this->request('GET', 'account', [
            'timestamp' => round(microtime(true) * 1000),
        ]);

        foreach ($response['balances'] as $balance) {
            if ($balance['asset'] == $asset) {
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
        return $this->request('GET', 'klines', [
            'symbol' => $symbol,
            'interval' => $interval,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'limit' => $limit,
        ]);
    }

    /**
     * Buy a symbol using Pair at current market price.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#new-order-trade
     *
     * @param string $symbol BTCUSDT
     * @param float $quoteQuantity Amount in USDT
     * @return array
     */
    public function buy($symbol, $quoteQuantity)
    {
        $symbolInfo = $this->getSymbolInfo($symbol);

        $params = [
            'symbol' => $symbol,
            'side' => 'BUY',
            'type' => 'MARKET',
            'quoteOrderQty' => $quoteQuantity,
        ];

        // Check balance
        $balance = $this->getBalance($symbolInfo['quoteAsset']);
        if ($balance < $quoteQuantity) {
            throw new Exception('Not enough ' . $symbolInfo['quoteAsset'] . ' balance: ' . $balance);
        }

        // Check Min Trade Quantity
        $minNotional = $this->getSymbolFilterValue($symbolInfo, 'MIN_NOTIONAL', 'minNotional');
        if ($quoteQuantity < $minNotional) {
            throw new Exception('Quantity below min of ' . $minNotional . ' ' . $symbolInfo['quoteAsset']);
        }

        return $this->request('POST', 'order', $params);
    }

    /**
     * Perform an OCO (One Cancels the Other) trade.
     *
     * https://binance-docs.github.io/apidocs/spot/en/#new-oco-trade
     * https://academy.binance.com/en/articles/what-is-an-oco-order
     *
     * Time in Force:
     * - GTC (Good-Til-Canceled) orders are effective until they are executed or canceled.
     * - IOC (Immediate or Cancel) orders fills all or part of an order immediately and cancels the remaining part of the order.
     * - FOK (Fill or Kill) orders fills all in its entirety, otherwise, the entire order will be cancelled.
     *
     * @param string $symbol (BTCUSDT)
     * @param float $quantity
     * @param float $price
     * @param float $stopPrice
     * @return array
     */
    public function sellWithStopLoss($symbol, $quantity, $price, $stopPrice)
    {
        $symbolInfo = $this->getSymbolInfo($symbol);

        return $this->request('POST', 'order', [
            'symbol' => $symbol,
            'side' => 'SELL',
            'type' => 'STOP_LOSS_LIMIT',
            'quantity' => $this->formatQuantity($symbolInfo, $quantity),
            'price' => $this->formatPrice($symbolInfo, $price),
            'stopPrice' => $this->formatPrice($symbolInfo, $stopPrice),
            'timeInForce' => 'GTC',
        ]);
    }

    /**
     * Perform an API request.
     *
     * @param  string $method
     * @param  string $endpoint
     * @param  array  $params
     * @return array
     */
    private function request($method, $endpoint, $params = [])
    {
        $method = strtolower($method);

        if ($this->endpointRequiresAuth($endpoint)) {
            $params['timestamp'] = number_format(microtime(true) * 1000, 0, '.', '');
            $params['signature'] = hash_hmac('sha256', http_build_query($params, '', '&'), $this->secret);
        }

        $request = Http::withHeaders(['X-MBX-APIKEY' => $this->key]);

        if ($method == 'post') {
            $request->asForm();
        }

        $response = $request->$method($this->getApiUrl() . $endpoint, $params);

        if (isset($response['code']) && $response['code'] < 0) {
            throw new Exception('Binance error: ' . $response['msg']);
        }

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
        $parts = explode('/', $endpoint);
        $endpoint = $parts[0];

        return in_array($endpoint, [
            'account',
            'order',
        ]);
    }

    /**
     * Format a price based on Exchange Information.
     *
     * @param array $symbolInfo
     * @param float $value
     * @return float
     */
    private function formatPrice($symbolInfo, $value)
    {
        $minPrice = $this->getSymbolFilterValue($symbolInfo, 'PRICE_FILTER', 'minPrice') ?: 0.00000001;

        $precision = strlen(substr(strrchr(rtrim($minPrice, '0'), '.'), 1));

        $value = round((($value / $minPrice) | 0) * $minPrice, $precision);

        return number_format($value, $precision, '.', '');
    }

    /**
     * Format a quantity based on Exchange Information.
     *
     * @param array $symbolInfo
     * @param float $value
     * @return float
     */
    private function formatQuantity($symbolInfo, $value)
    {
        $stepSize = $this->getSymbolFilterValue($symbolInfo, 'LOT_SIZE', 'stepSize') ?: 0.1;

        $precision = strlen(substr(strrchr(rtrim($stepSize, '0'), '.'), 1));

        $value = round((($value / $stepSize) | 0) * $stepSize, $precision);

        return number_format($value, $precision, '.', '');
    }

    /**
     * Get Symbol Exchange Information.
     *
     * @param string $symbol
     * @return array
     */
    private function getSymbolInfo($symbol)
    {
        $symbol = strtoupper($symbol);

        foreach ($this->exchangeInfo['symbols'] as $info) {
            if ($info['symbol'] === $symbol) {
                return $info;
            }
        }
    }

    /**
     * Get a filter value from a symbol info.
     *
     * @param array $symbolInfo
     * @param string $filterType
     * @param string $index
     * @return string
     */
    private function getSymbolFilterValue($symbolInfo, $filterType, $index)
    {
        foreach ($symbolInfo['filters'] as $filter) {
            if ($filter['filterType'] === $filterType) {
                return $filter[$index];
            }
        }
    }
}
