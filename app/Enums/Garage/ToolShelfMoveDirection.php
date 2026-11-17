<?php

namespace App\Enums\Garage;

/**
 * One step a tool shelf takes in the order of its garage.
 */
enum ToolShelfMoveDirection: string
{
    case Up = 'up';
    case Down = 'down';

    public function offset(): int
    {
        return $this === self::Up ? -1 : 1;
    }
}
