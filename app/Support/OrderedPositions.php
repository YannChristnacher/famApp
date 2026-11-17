<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Order of the items of a member (bookshelves, tool shelves) kept in an
 * integer `position` column, compact from 1 to n and unique per member.
 *
 * The callers read and save the items under the lock on their owner.
 */
final class OrderedPositions
{
    /**
     * The items in the same order, with the given one swapped with its
     * neighbour (offset -1: the previous one, +1: the next one); nothing
     * changes at the bounds or when the item is not in the list.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $ordered
     * @param  TModel  $item
     * @return Collection<int, TModel>
     */
    public static function swapWithNeighbour(Collection $ordered, Model $item, int $offset): Collection
    {
        $items = $ordered->values()->all();
        $index = collect($items)->search(fn (Model $other) => $other->is($item));
        $target = $index === false ? false : $index + $offset;

        if ($index !== false && $target >= 0 && $target < count($items)) {
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        }

        return new Collection($items);
    }

    /**
     * Numbers the items 1..n in the given order. The items that change place
     * first go above every current and final position, so that no two items
     * ever share a position (unique index owner + position). The moving
     * items keep their index in the full list, which is their final place.
     *
     * @param  Collection<int, covariant Model>  $ordered
     */
    public static function save(Collection $ordered): void
    {
        $ordered = $ordered->values();
        $moving = $ordered->reject(fn (Model $item, int $index) => self::positionOf($item) === $index + 1);
        $ceiling = max($ordered->count(), (int) $ordered->max(fn (Model $item) => self::positionOf($item)));

        $moving->each(fn (Model $item, int $index) => $item->setAttribute('position', $ceiling + $index + 1)->save());
        $moving->each(fn (Model $item, int $index) => $item->setAttribute('position', $index + 1)->save());
    }

    private static function positionOf(Model $item): int
    {
        return (int) $item->getAttribute('position');
    }
}
