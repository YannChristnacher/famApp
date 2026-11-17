<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

/**
 * A book of the signed-in user, with a cover stored on the public disk.
 */
function signedInBookWithCover(): Book
{
    $user = signIn();
    Storage::disk('public')->put('covers/original.jpg', 'cover');

    return Book::factory()->for($user)->create([
        'title' => 'Fondation',
        'author' => 'Isaac Asimov',
        'isbn' => '9782070360536',
        'cover_path' => 'covers/original.jpg',
    ]);
}

it('updates the book and synchronizes its bookshelves, keeping the cover', function () {
    $book = signedInBookWithCover();
    $kept = Bookshelf::factory()->for($book->user)->create(['name' => 'Science-fiction']);
    $removed = Bookshelf::factory()->for($book->user)->create(['name' => 'À lire']);
    $added = Bookshelf::factory()->for($book->user)->create(['name' => 'Coup de cœur']);
    $book->bookshelves()->attach([$kept->id, $removed->id]);

    patch(route('library.books.update', $book), [
        'title' => ' Fondation et Empire ',
        'author' => 'Isaac Asimov',
        'isbn' => '2-07-036822-x',
        'bookshelf_ids' => [$kept->id, $added->id],
    ])
        ->assertRedirect(route('library.books.show', $book))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le livre « Fondation et Empire » a été modifié.']);

    $book->refresh();
    expect($book->title)->toBe('Fondation et Empire')
        ->and($book->isbn)->toBe('207036822X')
        ->and($book->bookshelves->pluck('name')->all())->toBe(['Science-fiction', 'Coup de cœur'])
        ->and($book->cover_path)->toBe('covers/original.jpg');
    Storage::disk('public')->assertExists('covers/original.jpg');
});

it('removes the book from every bookshelf and its ISBN when none is sent', function () {
    $book = signedInBookWithCover();
    $book->bookshelves()->attach(Bookshelf::factory()->for($book->user)->create());

    patch(route('library.books.update', $book), ['title' => 'Fondation', 'author' => 'Isaac Asimov', 'isbn' => null])
        ->assertSessionHasNoErrors();

    $book->refresh();
    expect($book->bookshelves)->toBeEmpty()
        ->and($book->isbn)->toBeNull();
});

it('replaces the cover and deletes the previous file', function () {
    $book = signedInBookWithCover();

    // Multipart form sent by the browser: POST spoofing PATCH.
    post(route('library.books.update', $book), [
        '_method' => 'patch',
        'title' => 'Fondation',
        'author' => 'Isaac Asimov',
        'cover' => UploadedFile::fake()->image('new.png', 1600, 1000),
    ])->assertSessionHasNoErrors();

    $book->refresh();
    expect($book->cover_path)->not->toBe('covers/original.jpg')
        ->and(Storage::disk('public')->allFiles())->toBe([$book->cover_path])
        ->and(storedImage((string) $book->cover_path))->toBe([800, 500, 'mime' => 'image/jpeg']);
    Storage::disk('public')->assertMissing('covers/original.jpg');
});

it('removes the cover and deletes its file', function () {
    $book = signedInBookWithCover();

    patch(route('library.books.update', $book), ['title' => 'Fondation', 'author' => 'Isaac Asimov', 'remove_cover' => '1'])
        ->assertSessionHasNoErrors();

    expect($book->refresh()->cover_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps the new cover when a new file and the removal are both sent', function () {
    $book = signedInBookWithCover();

    patch(route('library.books.update', $book), [
        'title' => 'Fondation',
        'author' => 'Isaac Asimov',
        'remove_cover' => true,
        'cover' => UploadedFile::fake()->image('new.jpg', 300, 450),
    ])->assertSessionHasNoErrors();

    $book->refresh();
    expect($book->cover_path)->not->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([$book->cover_path]);
});

it('refuses an invalid update and changes nothing', function (array $payload, string $field) {
    $book = signedInBookWithCover();

    patch(route('library.books.update', $book), [...['title' => 'Fondation', 'author' => 'Isaac Asimov'], ...$payload])
        ->assertSessionHasErrors($field);

    $book->refresh();
    expect($book->title)->toBe('Fondation')
        ->and($book->isbn)->toBe('9782070360536')
        ->and($book->cover_path)->toBe('covers/original.jpg')
        ->and(Storage::disk('public')->allFiles())->toBe(['covers/original.jpg']);
})->with([
    'blank title' => [['title' => ''], 'title'],
    'invalid ISBN' => [['isbn' => '9782070360537'], 'isbn'],
    'remove_cover not a boolean' => [['remove_cover' => 'yes please'], 'remove_cover'],
    'PDF cover' => [['cover' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf')], 'cover'],
]);

it('refuses a bookshelf of another user and changes nothing', function () {
    $book = signedInBookWithCover();
    $own = Bookshelf::factory()->for($book->user)->create();
    $book->bookshelves()->attach($own);
    $foreign = Bookshelf::factory()->create();

    patch(route('library.books.update', $book), [
        'title' => 'Fondation',
        'author' => 'Isaac Asimov',
        'bookshelf_ids' => [$own->id, $foreign->id],
    ])->assertSessionHasErrors(['bookshelf_ids.1' => 'Une des étagères choisies n’existe pas.']);

    expect($book->bookshelves()->pluck('bookshelves.id')->all())->toBe([$own->id]);
});
