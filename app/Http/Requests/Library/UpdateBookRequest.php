<?php

namespace App\Http\Requests\Library;

use App\Models\Library\Book;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;

class UpdateBookRequest extends BookRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->book());
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Exists|File|Dimensions>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'remove_cover' => ['boolean'],
        ];
    }

    public function book(): Book
    {
        /** @var Book */
        return $this->route('book');
    }

    /**
     * A new cover replaces the current one anyway.
     */
    public function removesCover(): bool
    {
        return $this->boolean('remove_cover') && $this->cover() === null;
    }
}
