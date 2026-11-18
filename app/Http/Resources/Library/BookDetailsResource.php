<?php

namespace App\Http\Resources\Library;

use App\Models\Library\Book;
use Illuminate\Http\Request;

/**
 * A book with its comment (page and form of the book); the lists keep
 * BookResource.
 *
 * @mixin Book
 */
class BookDetailsResource extends BookResource
{
    /**
     * @return array{id: int, title: string, author: string, isbn: string|null, cover_url: string|null, bookshelves: list<array{id: int, name: string}>, comment: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'comment' => $this->comment,
        ];
    }
}
