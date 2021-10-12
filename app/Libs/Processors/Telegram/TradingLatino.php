<?php

namespace App\Libs\Processors\Telegram;

use App\Libs\Contracts\Interfaces\Processor;

/**
 * Trading Latino.
 */
class TradingLatino implements Processor
{
    /**
     * Parse a text from a Tweet.
     *
     * @param string $text
     * @return array|void
     */
    public static function parse(string $text)
    {

        $regex = '/🔰Comprar\s((\w|\d){3,5})\s\/\sUSDT\s/i';
        preg_match_all($regex, $text, $matches);
        if (empty($matches[0])) {
            return;
        }
        $symbol = $matches[1][0];

        $regex = '/🔸Precio\s+(.*?)\$\s–\s(.*?)\$/i';
        preg_match_all($regex, $text, $matches);
        if (empty($matches[0])) {
            return;
        }
        $entryMinInDollars = $matches[1][0];
        $entryMaxInDollars = $matches[1][0];

        return [
            'symbol' => $symbol,
            'entryMinInDollars' => $entryMinInDollars,
            'entryMaxInDollars' => $entryMaxInDollars,
        ];
    }
}
