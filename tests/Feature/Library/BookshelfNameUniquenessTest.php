<?php

use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Services\Library\BookshelfService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\post;

it('stores the normalized name next to the name', function (string $name, string $normalized) {
    $bookshelf = Bookshelf::factory()->create(['name' => $name]);

    expect($bookshelf->fresh()?->normalized_name)->toBe($normalized)
        ->and(Bookshelf::normalizeName($name))->toBe($normalized);
})->with([
    'case' => ['Coup de Cœur', 'coup de cœur'],
    'accented capitals' => ['ÉTÉ', 'été'],
    'inner spaces' => ["Romans   noirs\t", 'romans noirs'],
    'accents kept' => ['Café', 'café'],
]);

it('updates the normalized name on rename', function () {
    $bookshelf = Bookshelf::factory()->create(['name' => 'Polars']);

    $bookshelf->update(['name' => 'Romans  NOIRS']);

    expect($bookshelf->fresh()?->normalized_name)->toBe('romans noirs');
});

it('refuses in the database a name differing only by case or inner spaces, even without the validation rule', function (string $duplicate) {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);

    expect(fn () => Bookshelf::factory()->for($user)->create(['name' => $duplicate]))
        ->toThrow(UniqueConstraintViolationException::class);
})->with(['COUP DE CŒUR', 'coup  de cœur']);

it('reports a duplicate caught by the database as a validation error', function () {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $other = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    expect(fn () => app(BookshelfService::class)->create($user, 'FANTASY'))->toThrow(ValidationException::class)
        ->and(fn () => app(BookshelfService::class)->rename($other, 'fantasy'))->toThrow(ValidationException::class);

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars']);
});

it('treats accented and unaccented names as different', function () {
    $user = signIn();

    post(route('library.bookshelves.store'), ['name' => 'Café'])->assertSessionHasNoErrors();
    post(route('library.bookshelves.store'), ['name' => 'Cafe'])->assertSessionHasNoErrors();
    post(route('library.bookshelves.store'), ['name' => 'Été'])->assertSessionHasNoErrors();
    post(route('library.bookshelves.store'), ['name' => 'Ete'])->assertSessionHasNoErrors();

    expect(bookshelfNames($user))->toBe(['Café', 'Cafe', 'Été', 'Ete']);
});

it('rejects with the validation rule a name differing only by case, accented capitals or inner spaces', function (string $duplicate) {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Été en  pente douce']);

    post(route('library.bookshelves.store'), ['name' => $duplicate])
        ->assertSessionHasErrors(['name' => 'Vous avez déjà une étagère nommée « Été en  pente douce ».']);

    expect(bookshelfNames($user))->toBe(['Été en  pente douce']);
})->with(['ÉTÉ EN PENTE DOUCE', ' été en pente   douce ']);

it('refuses two bookshelves of a member in the same position', function () {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy', 'position' => 1]);

    expect(fn () => Bookshelf::factory()->for($user)->create(['name' => 'Polars', 'position' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});
