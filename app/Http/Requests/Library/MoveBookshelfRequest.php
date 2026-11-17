<?php

namespace App\Http\Requests\Library;

use App\Enums\Library\BookshelfMoveDirection;
use App\Models\Library\Bookshelf;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MoveBookshelfRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('move', $this->bookshelf());
    }

    /**
     * @return array<string, array<int, string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(BookshelfMoveDirection::class)],
        ];
    }

    public function bookshelf(): Bookshelf
    {
        /** @var Bookshelf */
        return $this->route('bookshelf');
    }

    public function direction(): BookshelfMoveDirection
    {
        return BookshelfMoveDirection::from($this->string('direction')->value());
    }
}
