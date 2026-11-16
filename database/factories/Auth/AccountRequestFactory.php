<?php

namespace Database\Factories\Auth;

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRequest>
 */
class AccountRequestFactory extends Factory
{
    protected $model = AccountRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'message' => fake()->optional()->sentence(),
            'status' => AccountRequestStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AccountRequestStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AccountRequestStatus::Rejected]);
    }
}
