<?php

namespace App\Libs\Contracts\Interfaces;

interface Processor
{
    /**
     * Parse a text.
     *
     * @param string $text
     * @return array|void
     */
    public static function parse(string $text);
}
