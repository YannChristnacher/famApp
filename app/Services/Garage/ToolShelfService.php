<?php

namespace App\Services\Garage;

use App\Enums\Garage\ToolShelfMoveDirection;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Support\OrderedPositions;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tool shelves of a member and their order: positions always run from 1 to n
 * (App\Support\OrderedPositions).
 *
 * Every write first locks the row of the owner in `users`: it is the common
 * lock of all the writes on their garage, which exists even before the first
 * shelf (locking the shelves themselves would lock nothing then).
 */
class ToolShelfService
{
    /**
     * @return Collection<int, ToolShelf>
     */
    public function listFor(User $user): Collection
    {
        return $user->toolShelves()->get();
    }

    public function create(User $user, string $name): ToolShelf
    {
        return $this->writingFor($user->id, function () use ($user, $name) {
            $lastPosition = $this->shelvesOf($user->id)->max('position') ?? 0;

            $shelf = $user->toolShelves()->make(['name' => $name]);
            $shelf->position = $lastPosition + 1;
            $shelf->save();

            return $shelf;
        });
    }

    public function rename(ToolShelf $shelf, string $name): ToolShelf
    {
        return $this->writingFor($shelf->user_id, function () use ($shelf, $name) {
            $shelf->update(['name' => $name]);

            return $shelf;
        });
    }

    public function delete(ToolShelf $shelf): void
    {
        $this->writingFor($shelf->user_id, function () use ($shelf) {
            $remaining = $this->shelvesOf($shelf->user_id)
                ->reject(fn (ToolShelf $other) => $other->is($shelf));

            $shelf->delete();
            OrderedPositions::save($remaining);
        });
    }

    /**
     * Swaps the shelf with its neighbour; nothing happens at the bounds.
     */
    public function move(ToolShelf $shelf, ToolShelfMoveDirection $direction): void
    {
        $this->writingFor($shelf->user_id, function () use ($shelf, $direction) {
            OrderedPositions::save(
                OrderedPositions::swapWithNeighbour($this->shelvesOf($shelf->user_id), $shelf, $direction->offset()),
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
     * @return Collection<int, ToolShelf>
     */
    private function shelvesOf(int $userId): Collection
    {
        return ToolShelf::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
