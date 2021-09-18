<?php

namespace App\Libs\Contracts\Interfaces;

/**
 * Processor Interface.
 *
 * Extract signals from a given text.
 */
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
