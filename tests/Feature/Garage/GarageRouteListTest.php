<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

$garageRoutes = [
    'garage.index',
    'garage.shelves.index',
    'garage.shelves.store',
    'garage.shelves.update',
    'garage.shelves.destroy',
    'garage.shelves.move',
];

it('exposes the garage routes to signed-in users', function (bool $admin) use ($garageRoutes) {
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    $response = get(route('garage.shelves.index'))->assertOk();

    foreach ($garageRoutes as $name) {
        $response->assertSee("\"{$name}\":", false);
    }
})->with(['user' => [false], 'admin' => [true]]);

it('never exposes the garage routes to a guest', function () {
    get(route('login'))->assertOk()
        ->assertDontSee('"garage.', false)
        ->assertDontSee('garage\\/', false);
});

it('declares the garage routes in the signed-in group only', function () use ($garageRoutes) {
    expect(array_values(array_intersect(config('ziggy.groups.auth'), $garageRoutes)))->toBe($garageRoutes)
        ->and(array_values(array_intersect(config('ziggy.only'), $garageRoutes)))->toBe($garageRoutes)
        ->and(array_intersect(config('ziggy.groups.guest'), $garageRoutes))->toBeEmpty()
        ->and(array_intersect(config('ziggy.groups.admin'), $garageRoutes))->toBeEmpty();
});
