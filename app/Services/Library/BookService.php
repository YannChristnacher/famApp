<?php

namespace App\Services\Library;

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Books of a member. A new cover file is written before the transaction and
 * removed if it fails; a replaced cover file is removed only once the
 * transaction is committed.
 */
class BookService
{
    public function __construct(private readonly BookCoverService $covers) {}

    /**
     * The books of the member, by title, optionally on one of their bookshelves.
     *
     * @return Collection<int, Book>
     */
    public function listFor(User $user, ?Bookshelf $bookshelf = null): Collection
    {
        return $user->books()
            ->when($bookshelf, fn ($query, Bookshelf $bookshelf) => $query->whereRelation('bookshelves', 'bookshelves.id', $bookshelf->id))
            ->with('bookshelves')
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{title: string, author: string, isbn: string|null}  $attributes
     * @param  list<int>  $bookshelfIds  Bookshelves of the member (validated).
     */
    public function create(User $user, array $attributes, array $bookshelfIds, ?UploadedFile $cover): Book
    {
        $coverPath = $cover === null ? null : $this->covers->store($cover);

        return $this->withNewCover($coverPath, fn () => DB::transaction(function () use ($user, $attributes, $bookshelfIds, $coverPath) {
            $book = $user->books()->make($attributes);
            $book->cover_path = $coverPath;
            $book->save();
            $book->bookshelves()->sync($bookshelfIds);

            return $book;
        }));
    }

    /**
     * @param  array{title: string, author: string, isbn: string|null}  $attributes
     * @param  list<int>  $bookshelfIds  Bookshelves of the member (validated).
     */
    public function update(Book $book, array $attributes, array $bookshelfIds, ?UploadedFile $cover, bool $removeCover): Book
    {
        $previousCover = $book->cover_path;
        $newCover = $cover === null ? null : $this->covers->store($cover);
        $replacesCover = $newCover !== null || $removeCover;

        $this->withNewCover($newCover, fn () => DB::transaction(function () use ($book, $attributes, $bookshelfIds, $newCover, $replacesCover) {
            $book->fill($attributes);
            if ($replacesCover) {
                $book->cover_path = $newCover;
            }
            $book->save();
            $book->bookshelves()->sync($bookshelfIds);
        }));

        if ($replacesCover) {
            $this->covers->delete($previousCover);
        }

        return $book;
    }

    public function delete(Book $book): void
    {
        $book->delete();
        $this->covers->delete($book->cover_path);
    }

    /**
     * Removes the freshly stored cover if the write fails.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function withNewCover(?string $coverPath, callable $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $exception) {
            $this->covers->delete($coverPath);

            throw $exception;
        }
    }
}
