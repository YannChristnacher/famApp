<?php

namespace Database\Factories\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookshelf>
 */
class BookshelfFactory extends Factory
{
    protected $model = Bookshelf::class;

    /**
     * Without an explicit position, the bookshelf goes after the other
     * bookshelves of its owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'position' => fn (array $attributes) => Bookshelf::query()
                ->where('user_id', $attributes['user_id'])
                ->max('position') + 1,
        ];
    }
}
