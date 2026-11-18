<?php

namespace Database\Factories\Auth;

use App\Enums\AccountRequestStatus;
use App\Models\Auth\AccountRequest;
use App\Models\User;
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

    /**
     * Approved: the account of the member was created from this request.
     */
    public function approvedFor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountRequestStatus::Approved,
            'name' => $user->name,
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AccountRequestStatus::Rejected]);
    }
}
