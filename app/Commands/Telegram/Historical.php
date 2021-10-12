<?php

namespace App\Commands\Telegram;

use App\Libs\Analyzers\Binance;
use App\Libs\Clients\Telegram;
use LaravelZero\Framework\Commands\Command;
use Exception;

class Historical extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'telegram:historical {group} {processor} {entry=avg : The entry price of the first candle. High, Low or AVG} {--limit=500} {--csv=}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Perform a check on historical data';

    /**
     * Telegram Client
     *
     * @var App\Libs\Clients\Telegram
     */
    private $telegram;

    /**
     * Binance Analyzer
     *
     * @var App\Libs\Analyzers\Binance
     */
    private $analyzer;

    /**
     * Telegram group
     *
     * @var string
     */
    private $telegramGroup;

    /**
     * Processor
     *
     * @var APp\Libs\Contracts\Interfaces\Processor
     */
    private $processor;

    /**
     * Tweet limit
     *
     * @var int
     */
    private $limit;

    /**
     * Entry strategy.
     *
     * @var string
     */
    private $entryStrategy;

    /**
     * CSV output path.
     *
     * @var string
     */
    private $csvPath;

    /**
     * Results
     *
     * @var array
     */
    private $results = [];

    /**
     * Initialization.
     */
    public function init()
    {
        $this->csvPath = $this->option('csv');
        $this->limit = $this->option('limit');

        $this->entryStrategy = strtolower($this->argument('entry'));
        if (!in_array($this->entryStrategy, ['avg', 'high', 'low'])) {
            throw new Exception('Invalid argument for entry. Use AVG, High or Low');
        }

        $this->telegramGroup = '-100' . $this->argument('group');
        $processor = 'App\\Libs\\Processors\\Telegram\\' . $this->argument('processor');
        if (!class_exists($processor)) {
            throw new Exception('Telegram processor not found');
        }
        $this->processor = $processor;
        $this->telegram = new Telegram();
        $this->analyzer = new Binance();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->init();

        $signals = $this->obtainSignals();
        if (!$signals) {
            return;
        }

        $this->analyzeSignals($signals);

        $this->showResults();

        if ($this->csvPath) {
            $this->storeCSV();
        }
    }

    /**
     * Obtain signals from Twitter.
     *
     * @return array
     */
    private function obtainSignals()
    {
        $this->info('Obtaining last ' . $this->limit . ' messages...');

        $messages = $this->telegram->historical($this->telegramGroup, $this->limit);

        $signals = [];
        foreach ($messages as $message) {
            $data = $this->processor::parse($message['text']);
            if (!$data) {
                continue;
            }

            $signals[] = [
                'symbol' => $data['symbol'],
                'entryMinInDollars' => $data['entryMinInDollars'],
                'entryMaxInDollars' => $data['entryMaxInDollars'],
                'date' => $message['created_at'],
            ];
        }

        $this->comment(count($signals) . ' Signals extracted');

        return $signals;
    }

    /**
     * Analyze signals.
     *
     * @param array $signals
     */
    private function analyzeSignals($signals)
    {
        $this->info('Analyzing signals...');

        $bar = $this->output->createProgressBar(count($signals));
        $bar->start();

        foreach ($signals as $signal) {
            $bar->advance();

            $this->results[] = $this->analyzer->analyze($signal['symbol'], $signal['date']);
        }

        $bar->finish();
    }

    /**
     * Display the results.
     */
    private function showResults()
    {
        $this->newLine();
        $this->newLine();

        $this->table(
            array_keys($this->results[0]),
            $this->results,
        );

        $this->newLine();
        $this->newLine();

        $totalTP = 0;
        $totalSL = 0;
        $profit = 0;
        $loss = 0;
        $total = 0;

        foreach ($this->results as $result) {
            // Ignore on going trades
            if (!isset($result['result'])) {
                continue;
            }

            $total++;

            if ($result['result'] == 'SL') {
                $totalSL++;
                $loss += env('STOP_LOSS');
            } elseif ($result['result'] == 'TP') {
                $totalTP++;
                $profit += env('TAKE_PROFIT');
            }
        }

        $this->info('Totals:');
        $this->comment('TP: ' . $totalTP . ' (' . round($totalTP / $total * 100) . ' %)');
        $this->comment('SL: ' . $totalSL . ' (' . round($totalSL  / $total * 100) . ' %)');
        $this->comment('Profit: ' . $profit . ' %');
        $this->comment('Loss: ' . $loss . ' %');
        $this->comment('Net Profit: ' . ($profit - $loss) . ' %');
    }

    /**
     * Store results to CSV.
     */
    private function storeCSV()
    {
        if (!is_writable(dirname($this->csvPath))) {
            throw new Exception('Directory ' . $this->csvPath . ' does not exists.');
        }

        if (!is_writable(dirname($this->csvPath))) {
            throw new Exception('Directory ' . $this->csvPath . ' is not writable.');
        }

        $fp = fopen($this->csvPath, 'w');
        fputcsv($fp, array_keys($this->results[0]), ';');
        foreach ($this->results as $result) {
            fputcsv($fp, $result, ';');
        }
        fclose($fp);

        $this->newLine();
        $this->newLine();

        $this->info('CSV stored at ' . $this->csvPath);
    }
}
