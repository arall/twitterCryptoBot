<?php

namespace App\Libs\Processors;

class CryptoEliz
{
    /**
     * Parse a text from a Tweet.
     *
     * @param string $text
     * @return array|void
     */
    public static function parse(string $text)
    {
        $regex = '/(#|\$)((\w|\d){3,5})\s+(👀|👁|🧐|☕️|↕️)($|\shttps:\/\/t\.co\/)/i';
        preg_match_all($regex, $text, $matches);

        if (empty($matches[0])) {
            return;
        }

        return ['symbol' => $matches[2][0]];
    }
}
