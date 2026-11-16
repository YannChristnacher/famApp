<?php

use App\Enums\Role;
use App\Models\User;

it('gives the user role by default', function () {
    $user = User::factory()->create();

    expect($user->fresh()?->role)->toBe(Role::User)
        ->and($user->isAdmin())->toBeFalse();
});

it('stores the user role in the database column default', function () {
    $user = User::query()->forceCreate([
        'name' => 'Paul',
        'email' => 'paul@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()?->role)->toBe(Role::User);
});

it('recognises an admin', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue();
});

it('does not allow the role to be mass assigned', function () {
    $user = User::query()->create([
        'name' => 'Paul',
        'email' => 'paul@example.com',
        'password' => 'password',
        'role' => Role::Admin->value,
    ]);

    expect($user->fresh()?->role)->toBe(Role::User);
});
