<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

$libraryRoutes = [
    'library.index',
    'library.bookshelves.index',
    'library.bookshelves.store',
    'library.bookshelves.update',
    'library.bookshelves.destroy',
    'library.bookshelves.move',
];

it('exposes the library routes to signed-in users', function (bool $admin) use ($libraryRoutes) {
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    $response = get(route('library.bookshelves.index'))->assertOk();

    foreach ($libraryRoutes as $name) {
        $response->assertSee("\"{$name}\":", false);
    }
})->with(['user' => [false], 'admin' => [true]]);

it('never exposes the library routes to a guest', function () {
    get(route('login'))->assertOk()
        ->assertDontSee('"library.', false)
        ->assertDontSee('library\\/', false);
});

it('declares the library routes in the signed-in group only', function () use ($libraryRoutes) {
    expect(array_values(array_intersect(config('ziggy.groups.auth'), $libraryRoutes)))->toBe($libraryRoutes)
        ->and(array_values(array_intersect(config('ziggy.only'), $libraryRoutes)))->toBe($libraryRoutes)
        ->and(array_intersect(config('ziggy.groups.guest'), $libraryRoutes))->toBeEmpty()
        ->and(array_intersect(config('ziggy.groups.admin'), $libraryRoutes))->toBeEmpty();
});
