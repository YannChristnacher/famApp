<?php

namespace App\Services\Library;

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Books of a member. A new cover file is written before the transaction and
 * removed if it fails; a replaced cover file is removed only once the
 * transaction is committed. The Google Books volume of a book is confirmed by
 * the server before anything is written.
 */
class BookService
{
    public function __construct(
        private readonly BookCoverService $covers,
        private readonly GoogleBooksService $googleBooks,
        private readonly GoogleBooksCoverService $googleCovers,
    ) {}

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
     * @param  array{title: string, author: string, isbn: string|null, comment: string|null}  $attributes
     * @param  list<int>  $bookshelfIds  Bookshelves of the member (validated).
     * @param  string|null  $googleVolumeId  Volume chosen by a lookup: its data is fetched by the server.
     *
     * @throws ValidationException When Google cannot confirm the volume (nothing is written).
     */
    public function create(User $user, array $attributes, array $bookshelfIds, BookCoverInput $cover, ?string $googleVolumeId = null): SavedBook
    {
        $volume = $this->trustedVolume($googleVolumeId, $user);
        $coverPath = $this->storeNewCover($cover, $volume, $user);

        $book = $this->withNewCover($coverPath, fn () => DB::transaction(function () use ($user, $attributes, $bookshelfIds, $coverPath, $volume) {
            $book = $user->books()->make($attributes);
            $book->cover_path = $coverPath;
            if ($volume !== null) {
                $book->google_books_data = $volume->raw;
            }
            $book->save();
            $book->bookshelves()->sync($bookshelfIds);

            return $book;
        }));

        return new SavedBook($book, $this->googleCoverMissing($cover, $volume, $coverPath));
    }

    /**
     * Without a Google volume, the raw data already stored is kept.
     *
     * @param  array{title: string, author: string, isbn: string|null, comment: string|null}  $attributes
     * @param  list<int>  $bookshelfIds  Bookshelves of the member (validated).
     *
     * @throws ValidationException When Google cannot confirm the volume (nothing is written).
     */
    public function update(Book $book, array $attributes, array $bookshelfIds, BookCoverInput $cover, ?string $googleVolumeId = null): SavedBook
    {
        $volume = $this->trustedVolume($googleVolumeId, $book->user);
        $previousCover = $book->cover_path;
        $newCover = $this->storeNewCover($cover, $volume, $book->user);
        $replacesCover = $newCover !== null || $cover->remove;

        $this->withNewCover($newCover, fn () => DB::transaction(function () use ($book, $attributes, $bookshelfIds, $newCover, $replacesCover, $volume) {
            $book->fill($attributes);
            if ($replacesCover) {
                $book->cover_path = $newCover;
            }
            if ($volume !== null) {
                $book->google_books_data = $volume->raw;
            }
            $book->save();
            $book->bookshelves()->sync($bookshelfIds);
        }));

        if ($replacesCover) {
            $this->covers->delete($previousCover);
        }

        return new SavedBook($book, $this->googleCoverMissing($cover, $volume, $newCover));
    }

    public function delete(Book $book): void
    {
        $book->delete();
        $this->covers->delete($book->cover_path);
    }

    private function trustedVolume(?string $googleVolumeId, User $user): ?GoogleBooksVolume
    {
        return $googleVolumeId === null ? null : $this->googleBooks->trustedVolume($googleVolumeId, $user);
    }

    /**
     * Stores the uploaded file, or else the Google cover when asked for.
     */
    private function storeNewCover(BookCoverInput $cover, ?GoogleBooksVolume $volume, User $user): ?string
    {
        if ($cover->file !== null) {
            return $this->covers->store($cover->file);
        }

        return $cover->fromGoogle && $volume !== null ? $this->googleCovers->store($volume, $user) : null;
    }

    private function googleCoverMissing(BookCoverInput $cover, ?GoogleBooksVolume $volume, ?string $storedCover): bool
    {
        return $cover->file === null && $cover->fromGoogle && $volume?->coverUrl !== null && $storedCover === null;
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
