<?php

namespace App\Libs\Analyzers;

use DateTime;
use DateTimeZone;
use App\Libs\Clients\Binance as Client;

class Binance
{
    /**
     * Candles Interval
     *
     * @var string
     */
    private $interval = '1m';

    /**
     * Candles limit
     *
     * @var string
     */
    private $limit = '1000';

    /**
     * Pair to use
     *
     * @var string
     */
    private $pair = 'USDT';

    /**
     * Take Profit %
     *
     * @var float
     */
    private $takeProfit;

    /**
     * Stop Loss %
     *
     * @var float
     */
    private $stopLoss;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
        $this->interval = env('BINANCE_INTERVAL');
        $this->pair = env('BINANCE_PAIR');
        $this->takeProfit = env('TAKE_PROFIT');
        $this->stopLoss = env('STOP_LOSS');
    }

    /**
     * Analyze a signal in specific date & time.
     *
     * @param  string $symbol
     * @param  string $dateTime Date in UTC
     * @param  string $entryStrategy Entry strategy (avg, high, low)
     * @return array
     */
    public function analyze($symbol, $dateTime, $entryStrategy = 'avg')
    {
        $entryStrategy = strtolower($entryStrategy);
        $date = new DateTime($dateTime, new DateTimeZone('UTC'));
        $dateStart = strtotime($dateTime) * 1000;
        $symbolBinance = $symbol . $this->pair;

        $klines = $this->client->klines($symbolBinance, $this->interval, $dateStart, null, '1');

        if ($entryStrategy == 'avg') {
            $entry = ($klines[0][2] + $klines[0][3]) / 2;
        } elseif ($entryStrategy == 'high') {
            $entry = $klines[0][2];
        } elseif ($entryStrategy == 'low') {
            $entry = $klines[0][3];
        }

        // Exits
        $takeProfit = $entry * $this->takeProfit;
        $stopLoss = $entry - (($entry * $this->stopLoss) - $entry);

        $entry = [
            'symbol' => $symbol,
            'date' => $date->format('Y-m-d H:i:s'),
            'open' => $klines[0][1],
            'high' => $klines[0][2],
            'low' => $klines[0][3],
            'close' => $klines[0][4],
            'entry' => $entry,
            'take_profit' => $takeProfit,
            'stop_loss' => $stopLoss,
        ];

        // Simulate next 1k intervals
        $klines = $this->client->klines($symbolBinance, $this->interval, $dateStart, null, $this->limit);
        foreach ($klines as $i => $kline) {

            $entry['intervals'] = $i;

            // Stop loss reached?
            if ($kline[3] <= $stopLoss) {
                $entry['result'] = 'SL';
                break;
            }

            // Take Profit reached?
            if ($kline[2] >= $takeProfit) {
                $entry['result'] = 'TP';
                break;
            }
        }

        return $entry;
    }
}
