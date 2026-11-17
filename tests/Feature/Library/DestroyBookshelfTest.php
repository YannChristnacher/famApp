<?php

use App\Models\Library\Bookshelf;
use App\Models\User;

use function Pest\Laravel\delete;

it('deletes a bookshelf and closes the gap in the positions', function () {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $middle = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);

    delete(route('library.bookshelves.destroy', $middle))
        ->assertRedirect(route('library.bookshelves.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère « Polars » a été supprimée."]);

    expect(Bookshelf::query()->find($middle->id))->toBeNull()
        ->and(bookshelfNames($user))->toBe(['Fantasy', 'Coup de cœur']);
});

it('deletes the first and the last bookshelf', function () {
    $user = signIn();
    $first = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    $last = Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);

    delete(route('library.bookshelves.destroy', $first));
    expect(bookshelfNames($user))->toBe(['Polars', 'Coup de cœur']);

    delete(route('library.bookshelves.destroy', $last));
    expect(bookshelfNames($user))->toBe(['Polars']);
});

it('leaves the bookshelves of other users untouched', function () {
    $user = signIn();
    $other = User::factory()->create();
    Bookshelf::factory()->for($other)->create(['name' => 'A']);
    Bookshelf::factory()->for($other)->create(['name' => 'B']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);

    delete(route('library.bookshelves.destroy', $bookshelf));

    expect(bookshelfNames($user))->toBe([])
        ->and(bookshelfNames($other))->toBe(['A', 'B']);
});
