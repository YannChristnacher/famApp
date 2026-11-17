<?php

namespace App\Enums\Library;

/**
 * One step a bookshelf takes in the order of its library.
 */
enum BookshelfMoveDirection: string
{
    case Up = 'up';
    case Down = 'down';

    public function offset(): int
    {
        return $this === self::Up ? -1 : 1;
    }
}
