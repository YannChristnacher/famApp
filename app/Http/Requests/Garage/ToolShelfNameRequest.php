<?php

namespace App\Http\Requests\Garage;

use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Rules\Garage\UniqueToolShelfName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Name of a tool shelf, shared by the creation and the renaming: trimmed,
 * required, at most 100 characters, unique among the tool shelves of the member.
 */
abstract class ToolShelfNameRequest extends FormRequest
{
    public const NAME_MAX_LENGTH = 100;

    /**
     * The shelf being renamed, null on creation.
     */
    abstract protected function renamedShelf(): ?ToolShelf;

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
                new UniqueToolShelfName($user, $this->renamedShelf()),
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
