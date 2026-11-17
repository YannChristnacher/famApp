<?php

use App\Models\Library\Bookshelf;
use App\Models\User;

use function Pest\Laravel\patch;

it('renames a bookshelf with a trimmed name and keeps its position', function () {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    patch(route('library.bookshelves.update', $bookshelf), ['name' => '  Romans noirs '])
        ->assertRedirect(route('library.bookshelves.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère a été renommée en « Romans noirs »."]);

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Romans noirs']);
});

it('lets a bookshelf keep its name with another case', function () {
    $user = signIn();
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'fantasy']);

    patch(route('library.bookshelves.update', $bookshelf), ['name' => 'Fantasy'])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Fantasy']);
});

it('rejects an invalid name and changes nothing', function (mixed $name) {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    patch(route('library.bookshelves.update', $bookshelf), ['name' => $name])->assertSessionHasErrors('name');

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars']);
})->with([
    'missing' => [null],
    'blank' => ['  '],
    'too long' => [str_repeat('a', 101)],
    'name of another bookshelf' => [' FANTASY '],
]);

it('accepts the name of a bookshelf of another user', function () {
    $user = signIn();
    Bookshelf::factory()->for(User::factory())->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    patch(route('library.bookshelves.update', $bookshelf), ['name' => 'Fantasy'])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Fantasy']);
});
