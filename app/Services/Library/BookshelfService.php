<?php

namespace App\Services\Library;

use App\Enums\Library\BookshelfMoveDirection;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bookshelves of a member and their order: positions always run from 1 to n.
 * Every change locks the bookshelves of the owner, so that two concurrent
 * requests cannot mix up the positions.
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
        return $this->guardingUniqueName(fn () => DB::transaction(function () use ($user, $name) {
            $lastPosition = $this->lockedBookshelvesOf($user->id)->max('position') ?? 0;

            $bookshelf = $user->bookshelves()->make(['name' => $name]);
            $bookshelf->position = $lastPosition + 1;
            $bookshelf->save();

            return $bookshelf;
        }));
    }

    public function rename(Bookshelf $bookshelf, string $name): Bookshelf
    {
        return $this->guardingUniqueName(function () use ($bookshelf, $name) {
            $bookshelf->update(['name' => $name]);

            return $bookshelf;
        });
    }

    public function delete(Bookshelf $bookshelf): void
    {
        DB::transaction(function () use ($bookshelf) {
            $remaining = $this->lockedBookshelvesOf($bookshelf->user_id)
                ->reject(fn (Bookshelf $other) => $other->is($bookshelf))
                ->values();

            $bookshelf->delete();
            $this->savePositions($remaining);
        });
    }

    /**
     * Swaps the bookshelf with its neighbour; nothing happens at the bounds.
     */
    public function move(Bookshelf $bookshelf, BookshelfMoveDirection $direction): void
    {
        DB::transaction(function () use ($bookshelf, $direction) {
            $ordered = $this->lockedBookshelvesOf($bookshelf->user_id)->values()->all();
            $index = collect($ordered)->search(fn (Bookshelf $other) => $other->is($bookshelf));
            $target = $index === false ? false : $index + $direction->offset();

            if ($index !== false && $target >= 0 && $target < count($ordered)) {
                [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            }

            $this->savePositions(new Collection($ordered));
        });
    }

    /**
     * @return Collection<int, Bookshelf>
     */
    private function lockedBookshelvesOf(int $userId): Collection
    {
        return Bookshelf::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Numbers the bookshelves 1..n in the given order, saving only those that changed.
     *
     * @param  Collection<int, Bookshelf>  $ordered
     */
    private function savePositions(Collection $ordered): void
    {
        $ordered->values()->each(function (Bookshelf $bookshelf, int $index) {
            $bookshelf->position = $index + 1;
            $bookshelf->save();
        });
    }

    /**
     * A duplicate name that slipped past the validation (concurrent requests)
     * is reported as a validation error.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function guardingUniqueName(Closure $change): mixed
    {
        try {
            return $change();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Vous avez déjà une étagère portant ce nom.',
            ]);
        }
    }
}
