<?php

namespace App\Support;

/**
 * Shortens a text to a number of characters, the last one being an ellipsis.
 * Invalid UTF-8 (a log line read from disk) is replaced first, so the text
 * can always be encoded in JSON.
 */
final class TruncatedText
{
    public static function of(string $text, int $maxLength): string
    {
        $text = mb_scrub($text, 'UTF-8');

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }
}
