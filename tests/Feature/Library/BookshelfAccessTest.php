<?php

use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(Bookshelf): TestResponse<Response>>
 */
function bookshelfCalls(): array
{
    return [
        'library.index' => fn (Bookshelf $bookshelf) => get(route('library.index')),
        'index' => fn (Bookshelf $bookshelf) => get(route('library.bookshelves.index')),
        'store' => fn (Bookshelf $bookshelf) => post(route('library.bookshelves.store'), ['name' => 'Polars']),
        'update' => fn (Bookshelf $bookshelf) => patch(route('library.bookshelves.update', $bookshelf), ['name' => 'Polars']),
        'destroy' => fn (Bookshelf $bookshelf) => delete(route('library.bookshelves.destroy', $bookshelf)),
        'move' => fn (Bookshelf $bookshelf) => patch(route('library.bookshelves.move', $bookshelf), ['direction' => 'up']),
    ];
}

it('redirects a guest to the login page on every library route and changes nothing', function (string $call) {
    $owner = User::factory()->create();
    Bookshelf::factory()->for($owner)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($owner)->create(['name' => 'Coup de cœur']);

    bookshelfCalls()[$call]($bookshelf)->assertRedirect(route('login'));

    expect(bookshelfNames($owner))->toBe(['Fantasy', 'Coup de cœur'])
        ->and(Bookshelf::query()->count())->toBe(2);
})->with(array_keys(bookshelfCalls()));

it('hides the bookshelves of another user on every route that takes one', function (string $call) {
    $owner = User::factory()->create();
    Bookshelf::factory()->for($owner)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($owner)->create(['name' => 'Coup de cœur']);
    actingAs(User::factory()->create());

    bookshelfCalls()[$call]($bookshelf)->assertNotFound();

    expect(bookshelfNames($owner))->toBe(['Fantasy', 'Coup de cœur']);
})->with(['update', 'destroy', 'move']);

it('hides the bookshelves of another user from an admin too', function (string $call) {
    $owner = User::factory()->create();
    $bookshelf = Bookshelf::factory()->for($owner)->create(['name' => 'Fantasy']);
    actingAs(User::factory()->admin()->create());

    bookshelfCalls()[$call]($bookshelf)->assertNotFound();

    expect(bookshelfNames($owner))->toBe(['Fantasy']);
})->with(['update', 'destroy', 'move']);

it('lets a signed-in user reach every library route on their own bookshelf', function (string $call, int $status, ?string $redirect) {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur']);
    actingAs($user);

    $response = bookshelfCalls()[$call]($bookshelf)->assertStatus($status);

    if ($redirect !== null) {
        $response->assertRedirect(route($redirect))->assertSessionHasNoErrors();
    }
})->with([
    'library.index' => ['library.index', 302, 'library.books.index'],
    'index' => ['index', 200, null],
    'store' => ['store', 302, 'library.bookshelves.index'],
    'update' => ['update', 302, 'library.bookshelves.index'],
    'destroy' => ['destroy', 302, 'library.bookshelves.index'],
    'move' => ['move', 302, 'library.bookshelves.index'],
]);

it('answers 404 for a bookshelf that does not exist', function () {
    actingAs(User::factory()->create());

    patch(route('library.bookshelves.update', 999), ['name' => 'Polars'])->assertNotFound();
});
