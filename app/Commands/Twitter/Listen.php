<?php

namespace App\Commands\Twitter;

use App\Libs\Clients\Twitter;
use App\Libs\Clients\Binance;
use LaravelZero\Framework\Commands\Command;
use Exception;
use DateTime;
use Illuminate\Support\Facades\Log;

class Listen extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'twitter:listen {twitter} {processor}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Listen a live Tweeter feed';

    /**
     * Twitter Client
     *
     * @var App\Libs\Clients\Twitter
     */
    private $twitter;

    /**
     * Binance Client
     *
     * @var App\Libs\Clients\Binance
     */
    private $binance;

    /**
     * Twitter user
     *
     * @var string
     */
    private $twitterUser;

    /**
     * Processor
     *
     * @var APp\Libs\Contracts\Interfaces\Processor
     */
    private $processor;

    /**
     * Initialization.
     */
    public function init()
    {
        $this->twitterUser = $this->argument('twitter');

        $processor = 'App\\Libs\\Processors\\Twitter\\' . $this->argument('processor');
        if (!class_exists($processor)) {
            throw new Exception('Twitter processor not found');
        }
        $this->processor = $processor;

        $this->twitter = new Twitter();
        $this->binance = new Binance();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->init();

        $this->info('Listening live feed from ' . $this->twitterUser);

        Log::notice('Listening started...');

        $this->twitter->listen($this->twitterUser, function (array $tweet) {
            // Weird, but seems to happen... may worth investigating
            if (!isset($tweet['user']) || !isset($tweet['created_at'])) {
                return;
            }

            // Ignore mentions, rts...
            if ($tweet['user']['screen_name'] !== $this->twitterUser) {
                return;
            }

            $this->info('New Tweet: ' . $tweet['text']);

            $tweetDateTime = new DateTime($tweet['created_at']);
            $currentDateTime = new DateTime('NOW');
            $this->comment('Time: ' . $tweetDateTime->diff($currentDateTime)->format('%s') . ' seconds ago');

            $data = $this->processor::parse($tweet['text']);
            if (!$data) {
                $this->comment('Not a signal, ignoring');
                return;
            }

            $this->comment('Signal detected! ' . $data['symbol']);
            Log::notice('New signal: ' . $data['symbol']);

            $this->comment('Opening trade...');

            $response = $this->binance->buy('BTCUSDT', $data['symbol']);
            $quantity = $response['executedQty'];

            $avgEntryPrice = 0;
            foreach ($response['fills'] as $fill) {
                $avgEntryPrice += $fill['price'];
            }
            $avgEntryPrice = $avgEntryPrice / count($response['fills']);

            $this->comment('Bought ' . $quantity . ' at ' . $avgEntryPrice);
            Log::notice('Bought ' . $quantity . ' at ' . $avgEntryPrice);

            // Take Profit & Stop Loss order
            $takeProfitPrice = $avgEntryPrice * getenv('TAKE_PROFIT');
            $stopLossPrice = $avgEntryPrice - (($avgEntryPrice * getenv('STOP_LOSS')) - $avgEntryPrice);

            $this->info('Take Profit Price: ' . $takeProfitPrice);
            $this->info('Stop Loss Price: ' . $stopLossPrice);

            $this->binance->sellWithStopLoss('BTCUSDT', $quantity, $takeProfitPrice, $stopLossPrice);

            $this->comment('Trade created!');
            Log::notice('Take Profit Price: ' . $takeProfitPrice);
            Log::notice('Stop Loss Price: ' . $stopLossPrice);
        });
    }
}
