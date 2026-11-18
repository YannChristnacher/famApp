<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(Book): TestResponse<Response>>
 */
function bookCalls(): array
{
    return [
        'index' => fn (Book $book) => get(route('library.books.index')),
        'create' => fn (Book $book) => get(route('library.books.create')),
        'store' => fn (Book $book) => post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert']),
        'show' => fn (Book $book) => get(route('library.books.show', $book)),
        'edit' => fn (Book $book) => get(route('library.books.edit', $book)),
        'update' => fn (Book $book) => patch(route('library.books.update', $book), [
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'cover' => UploadedFile::fake()->image('cover.jpg', 100, 150),
            'remove_cover' => true,
        ]),
        'destroy' => fn (Book $book) => delete(route('library.books.destroy', $book)),
    ];
}

/**
 * A book with a cover on a bookshelf, all owned by a new user.
 */
function ownedBookWithCover(): Book
{
    Storage::fake('public');
    Storage::disk('public')->put('covers/original.jpg', 'cover');
    $book = Book::factory()->create(['title' => 'Fondation', 'author' => 'Isaac Asimov', 'cover_path' => 'covers/original.jpg']);
    $book->bookshelves()->attach(Bookshelf::factory()->for($book->user)->create());

    return $book;
}

function expectBookUnchanged(Book $book): void
{
    $fresh = $book->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh?->title)->toBe('Fondation')
        ->and($fresh?->cover_path)->toBe('covers/original.jpg')
        ->and($fresh?->bookshelves()->count())->toBe(1)
        ->and(Book::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('covers/original.jpg');
    expect(Storage::disk('public')->allFiles('covers'))->toBe(['covers/original.jpg']);
}

it('redirects a guest to the login page on every book route and changes nothing', function (string $call) {
    $book = ownedBookWithCover();

    bookCalls()[$call]($book)->assertRedirect(route('login'));

    expectBookUnchanged($book);
})->with(array_keys(bookCalls()));

it('hides the books of another user on every route that takes one', function (string $call, bool $admin) {
    $book = ownedBookWithCover();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    bookCalls()[$call]($book)->assertNotFound();

    expectBookUnchanged($book);
})->with(['edit', 'update', 'destroy'])->with(['user' => [false], 'admin' => [true]]);

// Since F11 every member reads the books of the others (read-only): the page
// opens, without any right to change the book.
it('lets another user or an admin read the page of a book without changing it', function (bool $admin) {
    $book = ownedBookWithCover();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    bookCalls()['show']($book)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Books/Show')
            ->where('book.id', $book->id)
            ->where('editable', false));

    expectBookUnchanged($book);
})->with(['user' => [false], 'admin' => [true]]);

it('lets the owner reach every book route on their own book', function (string $call, int $status) {
    $book = ownedBookWithCover();
    actingAs($book->user);

    bookCalls()[$call]($book)->assertStatus($status)->assertSessionHasNoErrors();
})->with([
    'index' => ['index', 200],
    'create' => ['create', 200],
    'store' => ['store', 302],
    'show' => ['show', 200],
    'edit' => ['edit', 200],
    'update' => ['update', 302],
    'destroy' => ['destroy', 302],
]);

it('answers 404 for a book that does not exist', function () {
    actingAs(User::factory()->create());

    get(route('library.books.show', 999))->assertNotFound();
});
