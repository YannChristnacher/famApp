<?php

namespace App\Support;

/**
 * Comparison form of a name a member types freely (bookshelves, tool shelves):
 * trimmed, repeated spaces reduced to one, lower case. Accents are kept, so
 * "Café" and "Cafe" stay two names; the column holding it is compared byte
 * for byte (utf8mb4_bin under MySQL).
 */
final class NormalizedName
{
    public static function of(string $name): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}
