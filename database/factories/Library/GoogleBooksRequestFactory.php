<?php

namespace Database\Factories\Library;

use App\Enums\Library\GoogleBooksRequestStatus;
use App\Enums\Library\GoogleBooksRequestType;
use App\Models\Library\GoogleBooksRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleBooksRequest>
 */
class GoogleBooksRequestFactory extends Factory
{
    protected $model = GoogleBooksRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'type' => GoogleBooksRequestType::Isbn,
            'query' => '9782070612758',
            'status' => GoogleBooksRequestStatus::Found,
            'http_status' => 200,
            'duration_ms' => fake()->numberBetween(80, 900),
        ];
    }
}
