<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

$familyRoutes = [
    'family.index',
    'family.members.show',
];

it('exposes the family routes to signed-in users', function (bool $admin) use ($familyRoutes) {
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    $response = get(route('family.index'))->assertOk();

    foreach ($familyRoutes as $name) {
        $response->assertSee("\"{$name}\":", false);
    }
})->with(['user' => [false], 'admin' => [true]]);

it('never exposes the family routes to a guest', function () {
    get(route('login'))->assertOk()
        ->assertDontSee('"family.', false)
        ->assertDontSee('family\\/', false);
});

it('declares the family routes in the signed-in group only', function () use ($familyRoutes) {
    expect(array_values(array_intersect(config('ziggy.groups.auth'), $familyRoutes)))->toBe($familyRoutes)
        ->and(array_values(array_intersect(config('ziggy.only'), $familyRoutes)))->toBe($familyRoutes)
        ->and(array_intersect(config('ziggy.groups.guest'), $familyRoutes))->toBeEmpty()
        ->and(array_intersect(config('ziggy.groups.admin'), $familyRoutes))->toBeEmpty();
});
