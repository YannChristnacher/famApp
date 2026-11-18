<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

it('renders the creation form with the bookshelves of the user only', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    Bookshelf::factory()->for(User::factory())->create(['name' => 'Étagère de Paul']);

    get(route('library.books.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Create')
            ->where('bookshelves', [['id' => $fantasy->id, 'name' => 'Fantasy', 'position' => 1]]));
});

it('renders the page of a book of the user', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $book = Book::factory()->for($user)->create(['title' => 'Le Hobbit', 'author' => 'J. R. R. Tolkien', 'isbn' => '207036822X']);
    $book->bookshelves()->attach($fantasy);

    get(route('library.books.show', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Show')
            ->where('book', [
                'id' => $book->id,
                'title' => 'Le Hobbit',
                'author' => 'J. R. R. Tolkien',
                'isbn' => '207036822X',
                'cover_url' => null,
                'bookshelves' => [['id' => $fantasy->id, 'name' => 'Fantasy']],
                'comment' => null,
            ])
            ->missing('book.google_books_data')
            ->missing('book.user_id'));
});

it('renders the edition form of a book with the bookshelves of the user', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $book = Book::factory()->for($user)->create(['title' => 'Le Hobbit']);

    get(route('library.books.edit', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Edit')
            ->where('book.id', $book->id)
            ->where('book.title', 'Le Hobbit')
            ->where('bookshelves', [['id' => $fantasy->id, 'name' => 'Fantasy', 'position' => 1]]));
});
