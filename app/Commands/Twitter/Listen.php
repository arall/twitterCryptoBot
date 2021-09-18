<?php

namespace App\Commands\Twitter;

use App\Libs\Clients\Twitter;
use App\Libs\Analyzers\Binance;
use LaravelZero\Framework\Commands\Command;
use Exception;

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

        $this->twitter->listen($this->twitterUser, function (array $tweet) {
            # Weird, but seems to happen... may worth investigating
            if (!isset($tweet['created_at'])) {
                return;
            }

            $this->info('New Tweet: ' . $tweet['text']);

            $data = $this->processor::parse($tweet['text']);
            if (!$data) {
                $this->comment('Not a signal, ignoring');
                return;
            }

            $this->comment('Signal detected! ' . $data['symbol']);

            $this->comment('Opening trade...');
            $trade = $this->binance->trade($data['symbol']);
            if (isset($trade['code']) && isset($trade['msg'])) {
                $this->error('Error: ' . $trade['msg']);
                return;
            }

            $this->comment('Trade created!');
        });
    }
}
