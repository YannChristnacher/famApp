<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\seed;

beforeEach(function () {
    config([
        'famapp.admin.name' => 'Yann',
        'famapp.admin.email' => 'admin@famapp.test',
        'famapp.admin.password' => 'mot-de-passe-admin',
    ]);
});

it('creates the admin account from the configuration', function () {
    seed(AdminUserSeeder::class);

    $admin = User::query()->sole();
    expect($admin->name)->toBe('Yann')
        ->and($admin->email)->toBe('admin@famapp.test')
        ->and($admin->role)->toBe(Role::Admin)
        ->and(Hash::check('mot-de-passe-admin', $admin->password))->toBeTrue();
});

it('is run by the database seeder', function () {
    seed(DatabaseSeeder::class);

    expect(User::query()->where('role', Role::Admin)->count())->toBe(1);
});

it('is idempotent and never overwrites an existing password', function () {
    seed(AdminUserSeeder::class);
    User::query()->sole()->update(['password' => 'mot-de-passe-change']);

    seed(AdminUserSeeder::class);

    $admin = User::query()->sole();
    expect(Hash::check('mot-de-passe-change', $admin->password))->toBeTrue()
        ->and($admin->role)->toBe(Role::Admin);
});

it('fails clearly when the admin email is missing', function () {
    config(['famapp.admin.email' => null]);

    expect(fn () => seed(AdminUserSeeder::class))
        ->toThrow(RuntimeException::class, 'ADMIN_EMAIL');
    expect(User::query()->count())->toBe(0);
});

it('fails clearly when the admin password is missing', function () {
    config(['famapp.admin.password' => '']);

    expect(fn () => seed(AdminUserSeeder::class))
        ->toThrow(RuntimeException::class, 'ADMIN_PASSWORD');
    expect(User::query()->count())->toBe(0);
});
