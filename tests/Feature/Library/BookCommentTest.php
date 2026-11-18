<?php

use App\Http\Requests\Library\BookRequest;
use App\Models\Library\Book;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

it('adds a nullable comment column to the books', function () {
    expect(Schema::hasColumn('books', 'comment'))->toBeTrue()
        ->and(Book::factory()->create()->refresh()->comment)->toBeNull();
});

it('creates a book with a trimmed comment, keeping its line breaks', function () {
    signIn();

    post(route('library.books.store'), [
        'title' => 'Dune',
        'author' => 'Frank Herbert',
        'comment' => "  Édition de poche\nCouverture abîmée  ",
    ])->assertSessionHasNoErrors();

    expect(Book::query()->sole()->comment)->toBe("Édition de poche\nCouverture abîmée");
});

it('creates a book without comment', function (array $comment) {
    signIn();

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', ...$comment])
        ->assertSessionHasNoErrors();

    expect(Book::query()->sole()->comment)->toBeNull();
})->with([
    'missing' => [[]],
    'null' => [['comment' => null]],
    'empty' => [['comment' => '']],
    'blank' => [['comment' => "  \n\t "]],
]);

it('replaces the comment of a book, or removes it when blank', function (?string $sent, ?string $stored) {
    $user = signIn();
    $book = Book::factory()->for($user)->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => 'Ancien']);

    patch(route('library.books.update', $book), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => $sent])
        ->assertRedirect(route('library.books.show', $book))
        ->assertSessionHasNoErrors();

    expect($book->refresh()->comment)->toBe($stored);
})->with([
    'another comment' => [' Prêté à mamie ', 'Prêté à mamie'],
    'empty' => ['', null],
    'blank' => ['   ', null],
    'null' => [null, null],
]);

it('limits the comment to 2000 characters', function () {
    signIn();
    $longest = str_repeat('é', BookRequest::COMMENT_MAX_LENGTH);

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => $longest.'é'])
        ->assertSessionHasErrors(['comment' => 'Le texte de commentaire ne peut pas contenir plus de 2000 caractères.']);
    expect(Book::query()->count())->toBe(0);

    post(route('library.books.store'), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => $longest])
        ->assertSessionHasNoErrors();
    expect(Book::query()->sole()->comment)->toBe($longest);
});

it('refuses a comment that is not a text, without changing the book', function () {
    $user = signIn();
    $book = Book::factory()->for($user)->create(['comment' => 'Ancien']);

    patch(route('library.books.update', $book), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => ['a']])
        ->assertSessionHasErrors('comment');
    patch(route('library.books.update', $book), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('comment');

    expect($book->refresh()->comment)->toBe('Ancien');
});

it('shows the comment on the page of a book to another member, and on its edition form to the owner', function () {
    $paul = User::factory()->create(['name' => 'Paul']);
    $book = Book::factory()->for($paul)->create(['comment' => "Dédicacé\npar l’auteur"]);

    signIn();
    get(route('library.books.show', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('book.comment', "Dédicacé\npar l’auteur")
            ->where('editable', false));

    actingAs($paul);
    get(route('library.books.edit', $book))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('book.comment', "Dédicacé\npar l’auteur"));
});

it('does not let another member change the comment', function (bool $admin) {
    $book = Book::factory()->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => 'Le mien']);
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    patch(route('library.books.update', $book), ['title' => 'Dune', 'author' => 'Frank Herbert', 'comment' => 'Piraté'])
        ->assertNotFound();

    expect($book->refresh()->comment)->toBe('Le mien');
})->with(['member' => [false], 'admin' => [true]]);

it('leaves the comment out of the lists and of the global search', function () {
    $paul = User::factory()->create(['name' => 'Paul']);
    Book::factory()->for($paul)->create(['title' => 'Dune', 'comment' => 'Zanzibar']);
    $user = signIn();
    Book::factory()->for($user)->create(['title' => 'Fondation', 'comment' => 'Zanzibar']);

    get(route('library.books.index'))
        ->assertInertia(fn (Assert $page) => $page->has('books', 1)->missing('books.0.comment'));
    get(route('family.members.show', $paul))
        ->assertInertia(fn (Assert $page) => $page->has('books', 1)->missing('books.0.comment'));

    getJson(route('search', ['q' => 'Zanzibar']))->assertOk()->assertExactJson(['groups' => []]);
    getJson(route('search', ['q' => 'Dune']))
        ->assertOk()
        ->assertJsonPath('groups.0.results.0.label', 'Dune')
        ->assertJsonMissingPath('groups.0.results.0.comment')
        ->assertDontSee('Zanzibar');
});
