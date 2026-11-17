<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;

use function Pest\Laravel\delete;

it('removes the books from a deleted bookshelf without deleting them', function () {
    $user = signIn();
    $deleted = Bookshelf::factory()->for($user)->create(['name' => 'À lire']);
    $kept = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $onBoth = Book::factory()->for($user)->create(['title' => 'Le Hobbit']);
    $onDeletedOnly = Book::factory()->for($user)->create(['title' => 'Dune']);
    $onBoth->bookshelves()->attach([$deleted->id, $kept->id]);
    $onDeletedOnly->bookshelves()->attach($deleted);

    delete(route('library.bookshelves.destroy', $deleted))->assertSessionHasNoErrors();

    expect(Book::query()->count())->toBe(2)
        ->and($onBoth->bookshelves()->pluck('name')->all())->toBe(['Fantasy'])
        ->and($onDeletedOnly->bookshelves()->count())->toBe(0)
        ->and($kept->books()->pluck('title')->all())->toBe(['Le Hobbit']);
});
