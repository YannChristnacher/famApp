<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

it('creates a book on bookshelves of the user, with a resized cover', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $favourites = Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);
    Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    $response = post(route('library.books.store'), [
        'title' => '  Le Hobbit ',
        'author' => ' J. R. R. Tolkien  ',
        'isbn' => '978-2-07-036822-8',
        'bookshelf_ids' => [$favourites->id, $fantasy->id],
        'cover' => UploadedFile::fake()->image('photo-de-mon-telephone.jpg', 2000, 3000),
    ]);

    $book = Book::query()->sole();
    $response->assertRedirect(route('library.books.show', $book))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le livre « Le Hobbit » a été ajouté.']);

    expect($book->user_id)->toBe($user->id)
        ->and($book->title)->toBe('Le Hobbit')
        ->and($book->author)->toBe('J. R. R. Tolkien')
        ->and($book->isbn)->toBe('9782070368228')
        ->and($book->google_books_data)->toBeNull()
        ->and($book->bookshelves->pluck('name')->all())->toBe(['Fantasy', 'Coup de cœur'])
        ->and($book->cover_path)->toStartWith('covers/')
        ->and($book->cover_path)->toEndWith('.jpg')
        ->and($book->cover_path)->not->toContain('photo-de-mon-telephone');

    Storage::disk('public')->assertExists((string) $book->cover_path);
    expect(Storage::disk('public')->allFiles())->toBe([$book->cover_path])
        ->and(storedImage((string) $book->cover_path))->toBe([800, 1200, 'mime' => 'image/jpeg']);
});

it('creates a book without bookshelf, ISBN nor cover', function () {
    $user = signIn();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => ''])
        ->assertSessionHasNoErrors();

    $book = Book::query()->sole();
    expect($book->user_id)->toBe($user->id)
        ->and($book->isbn)->toBeNull()
        ->and($book->cover_path)->toBeNull()
        ->and($book->bookshelves)->toBeEmpty()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('normalizes the ISBN', function (string $input, string $normalized) {
    signIn();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => $input])
        ->assertSessionHasNoErrors();

    expect(Book::query()->sole()->isbn)->toBe($normalized);
})->with([
    ['978-2-07-036822-8', '9782070368228'],
    ['2 07 036822 x', '207036822X'],
    ['2-07-036822-X', '207036822X'],
]);

it('accepts the same ISBN twice for the same user', function () {
    $user = signIn();
    Book::factory()->for($user)->create(['isbn' => '9782070368228']);

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9782070368228'])
        ->assertSessionHasNoErrors();

    expect(Book::query()->where('isbn', '9782070368228')->count())->toBe(2);
});

it('refuses an invalid book and creates nothing', function (array $payload, string $field, ?string $message) {
    signIn();

    $response = post(route('library.books.store'), [...['title' => 'Dune', 'author' => 'Frank Herbert'], ...$payload])
        ->assertSessionHasErrors($field);

    if ($message !== null) {
        $response->assertSessionHasErrors([$field => $message]);
    }
    expect(Book::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'missing title' => [['title' => null], 'title', 'Le champ titre est obligatoire.'],
    'blank title' => [['title' => '   '], 'title', 'Le champ titre est obligatoire.'],
    'too long title' => [['title' => str_repeat('a', 256)], 'title', null],
    'missing author' => [['author' => null], 'author', 'Le champ auteur est obligatoire.'],
    'blank author' => [['author' => '  '], 'author', 'Le champ auteur est obligatoire.'],
    'too long author' => [['author' => str_repeat('a', 256)], 'author', null],
    'wrong ISBN check digit' => [['isbn' => '978-2-07-036822-9'], 'isbn', 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
    'too short ISBN' => [['isbn' => '12345'], 'isbn', 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
    'ISBN with letters' => [['isbn' => 'ISBN 2070368228'], 'isbn', 'Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.'],
    'ISBN not a string' => [['isbn' => ['9782070368228']], 'isbn', null],
    'bookshelves not an array' => [['bookshelf_ids' => 'Fantasy'], 'bookshelf_ids', null],
    'unknown bookshelf' => [['bookshelf_ids' => [999]], 'bookshelf_ids.0', 'Une des étagères choisies n’existe pas.'],
    'bookshelf not an integer' => [['bookshelf_ids' => ['abc']], 'bookshelf_ids.0', null],
    'PDF cover' => [['cover' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf')], 'cover', null],
    'SVG cover' => [['cover' => UploadedFile::fake()->createWithContent('cover.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')], 'cover', null],
    'GIF cover' => [['cover' => UploadedFile::fake()->image('cover.gif', 100, 150)], 'cover', null],
    'too heavy cover' => [['cover' => UploadedFile::fake()->image('cover.jpg', 100, 150)->size(10 * 1024 + 1)], 'cover', null],
    'too large cover' => [['cover' => UploadedFile::fake()->image('cover.png', 6001, 10)], 'cover', null],
    'cover not a file' => [['cover' => 'cover.jpg'], 'cover', null],
]);

it('refuses a bookshelf of another user and creates nothing', function () {
    signIn();
    $foreign = Bookshelf::factory()->create();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'bookshelf_ids' => [$foreign->id]])
        ->assertSessionHasErrors(['bookshelf_ids.0' => 'Une des étagères choisies n’existe pas.']);

    expect(Book::query()->count())->toBe(0);
});

it('refuses the same bookshelf twice', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'bookshelf_ids' => [$fantasy->id, $fantasy->id]])
        ->assertSessionHasErrors('bookshelf_ids.0');

    expect(Book::query()->count())->toBe(0);
});

it('never lets the user fill the owner nor the Google Books data', function () {
    $user = signIn();
    $other = User::factory()->create();

    post(route('library.books.store'), [
        'title' => 'Dune',
        'author' => 'Frank Herbert',
        'user_id' => $other->id,
        'google_books_data' => ['id' => 'abc'],
        'cover_path' => 'covers/../../.env',
    ])->assertSessionHasNoErrors();

    $book = Book::query()->sole();
    expect($book->user_id)->toBe($user->id)
        ->and($book->google_books_data)->toBeNull()
        ->and($book->cover_path)->toBeNull();
});

it('leaves no cover file behind when saving the book fails', function () {
    $user = signIn();
    $fantasy = Bookshelf::factory()->for($user)->create();
    Book::saving(fn () => throw new RuntimeException('Database unavailable'));

    post(route('library.books.store'), [
        'title' => 'Dune',
        'author' => 'Frank Herbert',
        'bookshelf_ids' => [$fantasy->id],
        'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 1800),
    ])->assertServerError();

    expect(Book::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
