<?php

namespace App\Rules\Garage;

use App\Models\Garage\ToolShelf;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A member cannot have two tool shelves with the same normalized name
 * (ToolShelf::normalizeName(): case and repeated spaces ignored, accents
 * count), the same comparison as the unique index on normalized_name.
 */
class UniqueToolShelfName implements ValidationRule
{
    public function __construct(
        private readonly User $owner,
        private readonly ?ToolShelf $ignored = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $duplicate = $this->owner->toolShelves()
            ->where('normalized_name', ToolShelf::normalizeName($value))
            ->when($this->ignored, fn ($query, ToolShelf $ignored) => $query->whereKeyNot($ignored->getKey()))
            ->value('name');

        if ($duplicate !== null) {
            $fail("Vous avez déjà une étagère nommée « {$duplicate} ».");
        }
    }
}
