<?php

use App\Models\Library\Book;
use App\Models\User;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchTerm;
use App\Services\Library\BookSearchSource;
use Illuminate\Support\Facades\Storage;

/**
 * @return list<string>
 */
function bookLabels(string $term, ?User $searcher = null, int $limit = 5): array
{
    return app(BookSearchSource::class)
        ->search(new SearchTerm($term), $searcher ?? User::factory()->create(), $limit)
        ->map(fn (SearchResult $result) => $result->label)
        ->all();
}

it('describes its group in French', function () {
    $source = app(BookSearchSource::class);

    expect($source->key())->toBe('books')
        ->and($source->label())->toBe('Livres')
        ->and($source->icon())->toBe('i-lucide-book');
});

it('finds the books of every member by title, author or ISBN', function () {
    Book::factory()->create(['title' => 'Le Hobbit', 'author' => 'J. R. R. Tolkien', 'isbn' => '9782070368228']);
    Book::factory()->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9782221252055']);
    Book::factory()->create(['title' => 'Fondation', 'author' => 'Isaac Asimov']);

    expect(bookLabels('hobb'))->toBe(['Le Hobbit'])
        ->and(bookLabels('herbert'))->toBe(['Dune'])
        ->and(bookLabels('2070368'))->toBe(['Le Hobbit'])
        ->and(bookLabels('978-2-07-036822-8'))->toBe(['Le Hobbit'])
        ->and(bookLabels('978 2221'))->toBe(['Dune'])
        ->and(bookLabels('Zola'))->toBe([]);
});

it('ignores the case of the term', function () {
    Book::factory()->create(['title' => 'Le Hobbit', 'author' => 'J. R. R. Tolkien']);

    expect(bookLabels('TOLKIEN'))->toBe(['Le Hobbit'])
        ->and(bookLabels('le hobbit'))->toBe(['Le Hobbit']);
});

it('ignores the accents of the term under MySQL', function () {
    Book::factory()->create(['title' => 'L’Élève Ducobu']);

    expect(bookLabels('eleve'))->toBe(['L’Élève Ducobu']);
})->skip(fn () => DB::getDriverName() !== 'mysql', 'SQLite compares accents byte by byte (see doc/MEMORY.md).');

it('takes the LIKE wildcards of the term literally', function () {
    Book::factory()->create(['title' => '1000 recettes']);
    Book::factory()->create(['title' => '100% végétal']);
    Book::factory()->create(['title' => 'axb']);
    Book::factory()->create(['title' => 'a_b']);
    Book::factory()->create(['title' => 'Oui!']);
    Book::factory()->create(['title' => 'C:\\temp']);

    expect(bookLabels('100%'))->toBe(['100% végétal'])
        ->and(bookLabels('a_b'))->toBe(['a_b'])
        ->and(bookLabels('i!'))->toBe(['Oui!'])
        ->and(bookLabels(':\\t'))->toBe(['C:\\temp']);
});

it('puts the titles starting with the term first, then by title', function () {
    Book::factory()->create(['title' => 'Voyage au centre de la Terre', 'author' => 'Jules Verne']);
    Book::factory()->create(['title' => 'Terre des hommes', 'author' => 'Saint-Exupéry']);
    Book::factory()->create(['title' => 'La Terre', 'author' => 'Émile Zola']);
    Book::factory()->create(['title' => 'Mars', 'author' => 'Terry Pratchett']);
    Book::factory()->create(['title' => 'Terminus', 'author' => 'Anonyme']);

    expect(bookLabels('ter'))->toBe(['Terminus', 'Terre des hommes', 'La Terre', 'Mars', 'Voyage au centre de la Terre']);
});

it('returns at most the given number of books, starting ones first', function () {
    Book::factory()->create(['title' => 'Un roman']);
    foreach (range(1, 6) as $number) {
        Book::factory()->create(['title' => "Roman {$number}"]);
    }

    expect(bookLabels('roman', limit: 5))->toBe(['Roman 1', 'Roman 2', 'Roman 3', 'Roman 4', 'Roman 5'])
        ->and(bookLabels('roman', limit: 8))->toBe(['Roman 1', 'Roman 2', 'Roman 3', 'Roman 4', 'Roman 5', 'Roman 6', 'Un roman']);
});

it('builds a minimal result pointing to the book page', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['name' => 'Paul']);
    $book = Book::factory()->for($owner)->create(['title' => 'Le Hobbit', 'author' => 'Tolkien', 'cover_path' => 'covers/abc.jpg']);
    $searcher = User::factory()->create();

    $result = app(BookSearchSource::class)->search(new SearchTerm('hobbit'), $searcher, 5)->sole();

    expect($result->toArray())->toBe([
        'type' => 'book',
        'id' => $book->id,
        'label' => 'Le Hobbit',
        'description' => 'Tolkien · chez Paul',
        'url' => "/library/books/{$book->id}",
        'image' => Storage::disk('public')->url('covers/abc.jpg'),
    ]);
});

it('says "chez vous" for a book of the searcher and has no image without a cover', function () {
    $searcher = User::factory()->create();
    Book::factory()->for($searcher)->create(['title' => 'Dune', 'author' => 'Herbert']);

    $result = app(BookSearchSource::class)->search(new SearchTerm('dune'), $searcher, 5)->sole();

    expect($result->description)->toBe('Herbert · chez vous')
        ->and($result->image)->toBeNull();
});
