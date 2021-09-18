# Twitter Crypto Bot

The main purpose of the bot is to listen trading signals (mostly bump and dumps) in Twitter feeds and, as quick as possible, perform market operations.

However, it's built to easily adapt to listen for other channels, for example Telegram.

The bot also contains commands to simulate past signals and generate statistics on the results. THis can be useful to tests different sources.

This bot is for my own personal use. I don't take any responsibility on your actions.

## Usage

First you will need a Twitter API credentials. You can obtain those at [Twitter Developer Portal](https://developer.twitter.com/en/portal/dashboard).
Then, you will also need Binance API credentials. Those can be obtained at [Binance API management](https://www.binance.com/en/my/settings/api-management).
Make sure the API key is allowed to perform market operations.
You can also use [Binance Testnet API credentials](https://testnet.binance.vision/) to perform trades in a test environment.

Those credentials need to be set in `.env` file. That file also contains other settings that need to be specified.

* BINANCE_TEST: Use Binance Testnet (true) or real API (false)
* BINANCE_PAIR: Pair to use (the USDT part of BTC/USDT, as some signals only specify the Crypto symbol)
* BINANCE_AMOUNT_PER_TRADE: Amount of Pair (USDT) to expend per each trade / signal. Keep in mind Binance minimum is 10$ - commissions, so at least set this to 15 to avoid issues.
* BINANCE_INTERVAL: Candles interval
* TAKE_PROFIT: Take Profit percentage
* STOP_LOSS: Stop Loss percentage

### Check

You can quickly check if all your APIs work by running:
```
php bot check
```

### Twitter - Historical

In order to simulate past signals from a Twitter feed:
```
php bot twitter:historical <twitter_username> <processor> <entry> <tweet_limit> <csv_path>
```

Where:
* Twitter: Twitter username to read from.
* Processor: Name of the Processor (Libs/Processors/Twitter/) class to use for extracting signals from the tweets.
* Entry: The entry price of the first candle. High, Low or AVG. As candles are read using 1 minute intervals, we need to define the entry price in the candle when the signals was sent. Optional. Default AVG.
* Tweet limit: Number of tweets to read. Optional. Default 500. 
* CSV Path: Path to store a CSV table with all the results. Optional.

For example:
```
php bot twitter:historical eliz883 cryptoeliz
```

### Twitter - Listen

Listen for new Tweets and quickly perform trading operations.

```
php bot twitter:listen <twitter_username> <processor>
```

Where:
* Twitter: Twitter username to read from.
* Processor: Name of the Processor (Libs/Processors/Twitter/) class to use for extracting signals from the tweets.

For example:
```
php bot twitter:historical eliz883 cryptoeliz
```

### Test - Trade

Performs a test trade on BTC/USDT.

```
php bot test:trade
```

## Class Structure
* Processors: Extract signals from a given text.
* Analyzers: Simulate a signal at a specific date-time, following a strategy.
