<?php

use App\Models\Library\Book;
use App\Models\Library\Bookshelf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\delete;

it('deletes the book, its links to the bookshelves and its cover file, but not the bookshelf', function () {
    Storage::fake('public');
    $user = signIn();
    Storage::disk('public')->put('covers/dune.jpg', 'cover');
    Storage::disk('public')->put('covers/other.jpg', 'cover');
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Science-fiction']);
    $book = Book::factory()->for($user)->create(['title' => 'Dune', 'cover_path' => 'covers/dune.jpg']);
    $other = Book::factory()->for($user)->create(['cover_path' => 'covers/other.jpg']);
    $book->bookshelves()->attach($bookshelf);
    $other->bookshelves()->attach($bookshelf);

    delete(route('library.books.destroy', $book))
        ->assertRedirect(route('library.books.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Le livre « Dune » a été supprimé.']);

    expect(Book::query()->pluck('id')->all())->toBe([$other->id])
        ->and(DB::table('book_bookshelf')->where('book_id', $book->id)->count())->toBe(0)
        ->and($bookshelf->books()->pluck('books.id')->all())->toBe([$other->id])
        ->and(bookshelfNames($user))->toBe(['Science-fiction'])
        ->and(Storage::disk('public')->allFiles())->toBe(['covers/other.jpg']);
});

it('deletes a book without cover', function () {
    $user = signIn();
    $book = Book::factory()->for($user)->create();

    delete(route('library.books.destroy', $book))->assertRedirect(route('library.books.index'));

    expect(Book::query()->count())->toBe(0);
});
