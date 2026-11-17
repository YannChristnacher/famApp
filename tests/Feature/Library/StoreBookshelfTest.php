<?php

use App\Models\Library\Bookshelf;
use App\Models\User;

use function Pest\Laravel\post;

it('creates the first bookshelf in position 1 with a trimmed name', function () {
    $user = signIn();
    post(route('library.bookshelves.store'), ['name' => '  Fantasy  '])
        ->assertRedirect(route('library.bookshelves.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère « Fantasy » a été créée."]);

    $bookshelf = Bookshelf::query()->sole();
    expect($bookshelf->user_id)->toBe($user->id)
        ->and($bookshelf->name)->toBe('Fantasy')
        ->and($bookshelf->position)->toBe(1);
});

it('adds a new bookshelf after the existing ones', function () {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    Bookshelf::factory()->for(User::factory())->create(['name' => 'Ailleurs', 'position' => 7]);

    post(route('library.bookshelves.store'), ['name' => 'Coup de cœur'])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars', 'Coup de cœur']);
});

it('rejects an invalid name and creates nothing', function (mixed $name) {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);

    post(route('library.bookshelves.store'), ['name' => $name])->assertSessionHasErrors('name');

    expect(bookshelfNames($user))->toBe(['Fantasy']);
})->with([
    'missing' => [null],
    'blank' => ['   '],
    'too long' => [str_repeat('a', 101)],
    'not a string' => [['Fantasy']],
    'same name' => ['Fantasy'],
    'other case' => ['fANTASY'],
    'surrounding spaces' => ['  fantasy '],
]);

it('accepts a name of 100 characters', function () {
    $user = signIn();
    post(route('library.bookshelves.store'), ['name' => str_repeat('a', 100)])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe([str_repeat('a', 100)]);
});

it('explains the errors in French', function () {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);

    post(route('library.bookshelves.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.']);
    post(route('library.bookshelves.store'), ['name' => 'FANTASY'])
        ->assertSessionHasErrors(['name' => 'Vous avez déjà une étagère nommée « Fantasy ».']);
    post(route('library.bookshelves.store'), ['name' => str_repeat('a', 101)])
        ->assertSessionHasErrors(['name' => 'Le texte de nom ne peut pas contenir plus de 100 caractères.']);
});

it('accepts a name already used by another user', function () {
    $user = signIn();
    Bookshelf::factory()->for(User::factory())->create(['name' => 'Fantasy']);

    post(route('library.bookshelves.store'), ['name' => 'Fantasy'])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Fantasy']);
});
