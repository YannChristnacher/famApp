<?php

use App\Models\Library\Bookshelf;
use App\Models\User;

use function Pest\Laravel\patch;

/**
 * Signs a member in with the bookshelves Fantasy, Polars and Coup de cœur, in that order.
 *
 * @return array{User, list<Bookshelf>}
 */
function signInWithThreeBookshelves(): array
{
    $user = signIn();
    $shelves = array_map(
        fn (string $name) => Bookshelf::factory()->for($user)->create(['name' => $name]),
        ['Fantasy', 'Polars', 'Coup de cœur'],
    );

    return [$user, $shelves];
}

it('moves a bookshelf up or down by one place', function (int $index, string $direction, array $expected) {
    [$user, $shelves] = signInWithThreeBookshelves();
    patch(route('library.bookshelves.move', $shelves[$index]), ['direction' => $direction])
        ->assertRedirect(route('library.bookshelves.index'))
        ->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe($expected);
})->with([
    'second up' => [1, 'up', ['Polars', 'Fantasy', 'Coup de cœur']],
    'second down' => [1, 'down', ['Fantasy', 'Coup de cœur', 'Polars']],
    'last up' => [2, 'up', ['Fantasy', 'Coup de cœur', 'Polars']],
    'first down' => [0, 'down', ['Polars', 'Fantasy', 'Coup de cœur']],
]);

it('leaves the order unchanged at the bounds', function (int $index, string $direction) {
    [$user, $shelves] = signInWithThreeBookshelves();
    patch(route('library.bookshelves.move', $shelves[$index]), ['direction' => $direction])
        ->assertRedirect(route('library.bookshelves.index'))
        ->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars', 'Coup de cœur']);
})->with([
    'first up' => [0, 'up'],
    'last down' => [2, 'down'],
]);

it('rejects a missing or unknown direction', function (mixed $direction) {
    [$user, $shelves] = signInWithThreeBookshelves();
    patch(route('library.bookshelves.move', $shelves[1]), ['direction' => $direction])
        ->assertSessionHasErrors('direction');

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars', 'Coup de cœur']);
})->with([
    'missing' => [null],
    'unknown' => ['left'],
    'array' => [['up']],
]);

it('compacts the positions when moving', function () {
    [$user, $shelves] = signInWithThreeBookshelves();
    $shelves[0]->update(['position' => 4]);
    $shelves[1]->update(['position' => 9]);
    $shelves[2]->update(['position' => 12]);

    patch(route('library.bookshelves.move', $shelves[2]), ['direction' => 'up']);

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Coup de cœur', 'Polars']);
});

it('only reorders the bookshelves of the owner', function () {
    [$user, $shelves] = signInWithThreeBookshelves();
    $other = User::factory()->create();
    Bookshelf::factory()->for($other)->create(['name' => 'A']);
    Bookshelf::factory()->for($other)->create(['name' => 'B']);

    patch(route('library.bookshelves.move', $shelves[0]), ['direction' => 'down']);

    expect(bookshelfNames($other))->toBe(['A', 'B']);
});
