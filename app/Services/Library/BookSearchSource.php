<?php

namespace App\Services\Library;

use App\Models\Library\Book;
use App\Models\User;
use App\Rules\Library\Isbn;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchSource;
use App\Services\Core\Search\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Books of every member, searched by title, author or ISBN (typed with or
 * without hyphens); the result leads to the page of the book.
 */
class BookSearchSource implements SearchSource
{
    public function __construct(private readonly BookCoverService $covers) {}

    public function key(): string
    {
        return 'books';
    }

    public function label(): string
    {
        return 'Livres';
    }

    public function icon(): string
    {
        return 'i-lucide-book';
    }

    public function search(SearchTerm $term, User $searcher, int $limit): Collection
    {
        $query = Book::query()
            ->select(['id', 'user_id', 'title', 'author', 'cover_path'])
            ->with('user:id,name');
        $isbn = $this->isbnTerm($term);

        $matches = function (Builder $any) use ($term, $isbn): void {
            $term->whereContains($any, 'title');
            $term->whereContains($any, 'author', 'or');
            if ($isbn !== null) {
                $isbn->whereContains($any, 'isbn', 'or');
            }
        };

        return $term->ranked($query, 'title', $matches, $limit)
            ->map(fn (Book $book) => new SearchResult(
                type: 'book',
                id: $book->id,
                label: $book->title,
                description: $book->author.' · '.$this->owner($book, $searcher),
                url: route('library.books.show', $book, false),
                image: $this->covers->url($book->cover_path),
            ))
            ->toBase();
    }

    /**
     * The term written like a stored ISBN, when it may be part of one.
     */
    private function isbnTerm(SearchTerm $term): ?SearchTerm
    {
        $isbn = Isbn::normalize($term->value);

        return preg_match('/^\d+X?$/', $isbn) === 1 && strlen($isbn) >= 2 ? new SearchTerm($isbn) : null;
    }

    private function owner(Book $book, User $searcher): string
    {
        return $book->isOwnedBy($searcher) ? 'chez vous' : 'chez '.$book->user->name;
    }
}
