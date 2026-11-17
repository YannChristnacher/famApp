<?php

namespace App\Http\Requests\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional filter of the list of books: `?bookshelf=<id>`, one of the
 * bookshelves of the member; anything else answers 404.
 */
class ListBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function bookshelfFilter(): ?Bookshelf
    {
        if (! $this->has('bookshelf')) {
            return null;
        }

        $id = $this->query('bookshelf');
        if (! is_string($id) || ! ctype_digit($id)) {
            throw (new ModelNotFoundException)->setModel(Bookshelf::class);
        }

        /** @var User $user */
        $user = $this->user();

        return $user->bookshelves()->whereKey((int) $id)->firstOrFail();
    }
}
