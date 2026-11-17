<?php

namespace App\Rules\Library;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A normalized ISBN-10 (9 digits and a digit or X, weighted sum modulo 11)
 * or ISBN-13 (13 digits, weights 1 and 3, sum modulo 10).
 */
class Isbn implements ValidationRule
{
    public const MESSAGE = 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.';

    /**
     * Removes hyphens and spaces and upper-cases the final X.
     */
    public static function normalize(string $isbn): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/u', '', $isbn));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! (self::isIsbn10($value) || self::isIsbn13($value))) {
            $fail(self::MESSAGE);
        }
    }

    private static function isIsbn10(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        foreach (str_split($isbn) as $index => $character) {
            $sum += ($character === 'X' ? 10 : (int) $character) * (10 - $index);
        }

        return $sum % 11 === 0;
    }

    private static function isIsbn13(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        foreach (str_split($isbn) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
