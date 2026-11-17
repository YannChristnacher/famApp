<?php

namespace App\Rules\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A member cannot have two bookshelves with the same name, whatever the case
 * (the name is trimmed before validation). Compared in PHP so that SQLite and
 * MySQL behave the same.
 */
class UniqueBookshelfName implements ValidationRule
{
    public function __construct(
        private readonly User $owner,
        private readonly ?Bookshelf $ignored = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $duplicate = $this->owner->bookshelves()
            ->when($this->ignored, fn ($query, Bookshelf $ignored) => $query->whereKeyNot($ignored->getKey()))
            ->pluck('name')
            ->first(fn (string $name) => self::normalize($name) === self::normalize($value));

        if ($duplicate !== null) {
            $fail("Vous avez déjà une étagère nommée « {$duplicate} ».");
        }
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
