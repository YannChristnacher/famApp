<?php

use App\Models\User;
use App\Support\ZiggyGroup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

$adminRoutes = [
    'admin.account-requests.index',
    'admin.account-requests.account.create',
    'admin.account-requests.account.store',
    'admin.account-requests.reject',
];

it('exposes the admin routes and the signed-in routes to an admin', function () use ($adminRoutes) {
    actingAs(User::factory()->admin()->create());

    $response = get(route('home'))->assertOk()
        ->assertSee('"home":', false)
        ->assertSee('"logout":', false)
        ->assertDontSee('"login":', false);

    foreach ($adminRoutes as $name) {
        $response->assertSee("\"{$name}\":", false);
    }
});

it('never exposes the admin routes to a regular user', function () {
    actingAs(User::factory()->create());

    get(route('home'))->assertOk()
        ->assertSee('"home":', false)
        ->assertDontSee('"admin.', false)
        ->assertDontSee('admin\\/', false);
});

it('never exposes the admin routes to a guest', function () {
    get(route('login'))->assertOk()
        ->assertDontSee('"admin.', false)
        ->assertDontSee('admin\\/', false);
});

it('keeps the admin routes in their own group, out of the default allowlist', function () use ($adminRoutes) {
    expect(config('ziggy.groups.admin'))->toBe($adminRoutes)
        ->and(array_intersect(config('ziggy.only'), $adminRoutes))->toBeEmpty()
        ->and(array_intersect(config('ziggy.groups.auth'), $adminRoutes))->toBeEmpty()
        ->and(array_intersect(config('ziggy.groups.guest'), $adminRoutes))->toBeEmpty();
});

it('picks the route groups from the role', function () {
    expect(ZiggyGroup::for(null))->toBe(['guest'])
        ->and(ZiggyGroup::for(User::factory()->make()))->toBe(['auth'])
        ->and(ZiggyGroup::for(User::factory()->admin()->make()))->toBe(['auth', 'admin']);
});
