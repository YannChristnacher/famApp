<?php

namespace App\Rules\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A member cannot have two bookshelves with the same normalized name
 * (Bookshelf::normalizeName(): case and repeated spaces ignored, accents
 * count), the same comparison as the unique index on normalized_name.
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
            ->where('normalized_name', Bookshelf::normalizeName($value))
            ->when($this->ignored, fn ($query, Bookshelf $ignored) => $query->whereKeyNot($ignored->getKey()))
            ->value('name');

        if ($duplicate !== null) {
            $fail("Vous avez déjà une étagère nommée « {$duplicate} ».");
        }
    }
}
