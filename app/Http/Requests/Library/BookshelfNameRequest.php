<?php

namespace App\Http\Requests\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Rules\Library\UniqueBookshelfName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Name of a bookshelf, shared by the creation and the renaming: trimmed,
 * required, at most 100 characters, unique among the bookshelves of the member.
 */
abstract class BookshelfNameRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 100;

    /**
     * The bookshelf being renamed, null on creation.
     */
    abstract protected function renamedBookshelf(): ?Bookshelf;

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => [
                'required',
                'string',
                'max:'.self::NAME_MAX_LENGTH,
                new UniqueBookshelfName($user, $this->renamedBookshelf()),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
