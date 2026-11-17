<?php

namespace App\Services\Library;

use App\Enums\Library\BookshelfMoveDirection;
use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Support\OrderedPositions;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bookshelves of a member and their order: positions always run from 1 to n
 * (App\Support\OrderedPositions).
 *
 * Every write first locks the row of the owner in `users`: it is the common
 * lock of all the writes on their bookshelves, which exists even before the
 * first bookshelf (locking the bookshelves themselves would lock nothing then).
 */
class BookshelfService
{
    /**
     * @return Collection<int, Bookshelf>
     */
    public function listFor(User $user): Collection
    {
        return $user->bookshelves()->get();
    }

    public function create(User $user, string $name): Bookshelf
    {
        return $this->writingFor($user->id, function () use ($user, $name) {
            $lastPosition = $this->bookshelvesOf($user->id)->max('position') ?? 0;

            $bookshelf = $user->bookshelves()->make(['name' => $name]);
            $bookshelf->position = $lastPosition + 1;
            $bookshelf->save();

            return $bookshelf;
        });
    }

    public function rename(Bookshelf $bookshelf, string $name): Bookshelf
    {
        return $this->writingFor($bookshelf->user_id, function () use ($bookshelf, $name) {
            $bookshelf->update(['name' => $name]);

            return $bookshelf;
        });
    }

    public function delete(Bookshelf $bookshelf): void
    {
        $this->writingFor($bookshelf->user_id, function () use ($bookshelf) {
            $remaining = $this->bookshelvesOf($bookshelf->user_id)
                ->reject(fn (Bookshelf $other) => $other->is($bookshelf));

            $bookshelf->delete();
            OrderedPositions::save($remaining);
        });
    }

    /**
     * Swaps the bookshelf with its neighbour; nothing happens at the bounds.
     */
    public function move(Bookshelf $bookshelf, BookshelfMoveDirection $direction): void
    {
        $this->writingFor($bookshelf->user_id, function () use ($bookshelf, $direction) {
            OrderedPositions::save(
                OrderedPositions::swapWithNeighbour($this->bookshelvesOf($bookshelf->user_id), $bookshelf, $direction->offset()),
            );
        });
    }

    /**
     * Runs a write in a transaction holding the lock on the owner; a duplicate
     * name caught by the unique index is reported as a validation error.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    private function writingFor(int $userId, Closure $write): mixed
    {
        try {
            return DB::transaction(function () use ($userId, $write) {
                User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

                return $write();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'normalized_name')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => 'Vous avez déjà une étagère portant ce nom.',
            ]);
        }
    }

    /**
     * Read under the lock on the owner.
     *
     * @return Collection<int, Bookshelf>
     */
    private function bookshelvesOf(int $userId): Collection
    {
        return Bookshelf::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
