<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Auth\AdminAccountService;
use Illuminate\Support\Facades\Hash;

it('creates an admin account', function () {
    $admin = app(AdminAccountService::class)->ensureAdmin('Yann', 'admin@famapp.test', 'secret-password');

    expect($admin->exists)->toBeTrue()
        ->and($admin->role)->toBe(Role::Admin)
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});

it('promotes an existing account without touching its name or password', function () {
    $user = User::factory()->create(['name' => 'Marie', 'email' => 'admin@famapp.test', 'password' => 'son-mot-de-passe']);

    $admin = app(AdminAccountService::class)->ensureAdmin('Yann', 'admin@famapp.test', 'autre');

    expect($admin->id)->toBe($user->id)
        ->and($admin->name)->toBe('Marie')
        ->and($admin->role)->toBe(Role::Admin)
        ->and(Hash::check('son-mot-de-passe', $admin->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
