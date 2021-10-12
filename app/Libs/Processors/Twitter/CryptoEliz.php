<?php

namespace App\Libs\Processors\Twitter;

use App\Libs\Contracts\Interfaces\Processor;

/**
 * CryptoEliz Twitter Processor.
 *
 * https://twitter.com/eliz883
 */
class CryptoEliz implements Processor
{
    /**
     * Regular expressions to match.
     */
    const REGEX = [
        '/(#|\$)((\w|\d){3,5})\s+(👀|👁|🧐|☕️|↕️)($|\shttps:\/\/t\.co\/)/i',
        '/(👀|👁|🧐|☕️|↕️)\s+(#|\$)((\w|\d){3,5})($|\shttps:\/\/t\.co\/)/i'
    ];

    /**
     * Parse a text from a Tweet.
     *
     * @param string $text
     * @return array|void
     */
    public static function parse(string $text)
    {
        foreach (self::REGEX as $regex) {
            preg_match_all($regex, $text, $matches);
            if (isset($matches[0]) && !empty($matches[0])) {
                return ['symbol' => $matches[2][0]];
            }
        }
    }
}
