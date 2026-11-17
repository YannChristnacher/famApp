<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('opens the library on the books of the member', function () {
    actingAs(User::factory()->create());

    get(route('library.index'))->assertRedirect(route('library.books.index'));
});

it('lists only the books of the signed-in user, by title, with their bookshelves and cover', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $favourites = Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);
    $hobbit = Book::factory()->for($user)->create([
        'title' => 'Le Hobbit',
        'author' => 'J. R. R. Tolkien',
        'isbn' => '9782267027006',
        'cover_path' => 'covers/hobbit.jpg',
    ]);
    $hobbit->bookshelves()->attach([$favourites->id, $fantasy->id]);
    $dune = Book::factory()->for($user)->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => null]);
    Book::factory()->create(['title' => 'Le livre de Paul']);
    actingAs($user);

    get(route('library.books.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Index')
            ->has('books', 2)
            ->where('books.0', [
                'id' => $dune->id,
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'isbn' => null,
                'cover_url' => null,
                'bookshelves' => [],
            ])
            ->where('books.1', [
                'id' => $hobbit->id,
                'title' => 'Le Hobbit',
                'author' => 'J. R. R. Tolkien',
                'isbn' => '9782267027006',
                'cover_url' => Storage::disk('public')->url('covers/hobbit.jpg'),
                'bookshelves' => [
                    ['id' => $fantasy->id, 'name' => 'Fantasy'],
                    ['id' => $favourites->id, 'name' => 'Coup de cœur'],
                ],
            ])
            ->where('bookshelves', [
                ['id' => $fantasy->id, 'name' => 'Fantasy', 'position' => 1],
                ['id' => $favourites->id, 'name' => 'Coup de cœur', 'position' => 2],
            ])
            ->where('filter', null));
});

it('filters the books by one of the bookshelves of the user', function () {
    $user = User::factory()->create();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $crime = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    Book::factory()->for($user)->create(['title' => 'Le Hobbit'])->bookshelves()->attach($fantasy);
    Book::factory()->for($user)->create(['title' => 'Bilbo'])->bookshelves()->attach([$fantasy->id, $crime->id]);
    Book::factory()->for($user)->create(['title' => 'Maigret'])->bookshelves()->attach($crime);
    Book::factory()->for($user)->create(['title' => 'Sans étagère']);
    actingAs($user);

    get(route('library.books.index', ['bookshelf' => $fantasy->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Index')
            ->where('books.0.title', 'Bilbo')
            ->where('books.1.title', 'Le Hobbit')
            ->has('books', 2)
            ->where('filter', $fantasy->id));
});

it('answers 404 when filtering by a bookshelf that is not one of the user', function (Closure $bookshelf) {
    actingAs(User::factory()->create());

    get(route('library.books.index', ['bookshelf' => $bookshelf()]))->assertNotFound();
})->with([
    'another user' => fn () => Bookshelf::factory()->create()->id,
    'unknown' => fn () => 999,
    'not an integer' => fn () => 'fantasy',
    'array' => fn () => [1],
]);

it('loads the list with the same number of queries whatever the number of books', function () {
    $countQueries = function (int $books): int {
        $user = User::factory()->create();
        $bookshelves = collect([Bookshelf::factory()->for($user)->create(), Bookshelf::factory()->for($user)->create()]);
        Book::factory()->for($user)->count($books)->create(['cover_path' => 'covers/x.jpg'])
            ->each(fn (Book $book) => $book->bookshelves()->attach($bookshelves));
        actingAs($user);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        get(route('library.books.index', ['bookshelf' => $bookshelves->first()?->id]))->assertOk();

        return $queries;
    };

    $withOneBook = $countQueries(1);
    expect($countQueries(5))->toBe($withOneBook);
});
