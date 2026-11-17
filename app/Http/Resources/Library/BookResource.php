<?php

namespace App\Http\Resources\Library;

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Services\Library\BookCoverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A book of the signed-in member; never the raw Google Books data.
 *
 * @mixin Book
 */
class BookResource extends JsonResource
{
    /**
     * @return array{id: int, title: string, author: string, isbn: string|null, cover_url: string|null, bookshelves: list<array{id: int, name: string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'cover_url' => app(BookCoverService::class)->url($this->cover_path),
            'bookshelves' => $this->bookshelves
                ->map(fn (Bookshelf $bookshelf) => ['id' => $bookshelf->id, 'name' => $bookshelf->name])
                ->values()
                ->all(),
        ];
    }
}
