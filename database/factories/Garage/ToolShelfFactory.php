<?php

namespace Database\Factories\Garage;

use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolShelf>
 */
class ToolShelfFactory extends Factory
{
    protected $model = ToolShelf::class;

    /**
     * Without an explicit position, the shelf goes after the other tool
     * shelves of its owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'position' => fn (array $attributes) => ToolShelf::query()
                ->where('user_id', $attributes['user_id'])
                ->max('position') + 1,
        ];
    }
}
