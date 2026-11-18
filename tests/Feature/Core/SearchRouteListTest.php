<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('exposes the search route to signed-in users', function (bool $admin) {
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    get(route('home'))->assertOk()->assertSee('"search":', false);
})->with(['user' => [false], 'admin' => [true]]);

it('never exposes the search route to a guest', function () {
    get(route('login'))->assertOk()->assertDontSee('"search":', false);
});

it('declares the search route in the signed-in group only', function () {
    expect(config('ziggy.groups.auth'))->toContain('search')
        ->and(config('ziggy.only'))->toContain('search')
        ->and(config('ziggy.groups.guest'))->not->toContain('search')
        ->and(config('ziggy.groups.admin'))->not->toContain('search');
});
