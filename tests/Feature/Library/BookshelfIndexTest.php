<?php

use App\Models\Library\Bookshelf;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists only the bookshelves of the signed-in user, by position', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $second = Bookshelf::factory()->for($user)->create(['name' => 'Polars', 'position' => 2]);
    Bookshelf::factory()->for($other)->create(['name' => 'Étagère de Paul', 'position' => 1]);
    $first = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy', 'position' => 1]);
    $third = Bookshelf::factory()->for($user)->create(['name' => 'Coup de cœur', 'position' => 3]);
    actingAs($user);

    get(route('library.bookshelves.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library/Bookshelves/Index')
            ->has('bookshelves', 3)
            ->where('bookshelves.0', ['id' => $first->id, 'name' => 'Fantasy', 'position' => 1])
            ->where('bookshelves.1', ['id' => $second->id, 'name' => 'Polars', 'position' => 2])
            ->where('bookshelves.2', ['id' => $third->id, 'name' => 'Coup de cœur', 'position' => 3]));
});

it('renders an empty library', function () {
    actingAs(User::factory()->create());

    get(route('library.bookshelves.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Library/Bookshelves/Index')->has('bookshelves', 0));
});

it('opens the library on the bookshelves page', function () {
    actingAs(User::factory()->admin()->create());

    get(route('library.index'))->assertRedirect(route('library.bookshelves.index'));
});
