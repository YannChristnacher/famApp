<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'can:admin'])
        ->get('/test-admin-only', fn () => 'zone admin')
        ->name('test.admin-only');
});

it('lets an admin through the admin gate', function () {
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($admin)->allows('admin'))->toBeTrue();
    actingAs($admin);
    get('/test-admin-only')->assertOk()->assertSee('zone admin');
});

it('forbids a regular user', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('admin'))->toBeFalse();
    actingAs($user);
    get('/test-admin-only')->assertForbidden();
});

it('redirects guests to the login page', function () {
    get('/test-admin-only')->assertRedirect(route('login'));
});
