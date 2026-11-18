<?php

use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists only the tool shelves of the signed-in user, by position', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $second = ToolShelf::factory()->for($user)->create(['name' => 'Manuel', 'position' => 2]);
    ToolShelf::factory()->for($other)->create(['name' => 'Étagère de Paul', 'position' => 1]);
    $first = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif', 'position' => 1]);
    $third = ToolShelf::factory()->for($user)->create(['name' => 'Jardin', 'position' => 3]);
    actingAs($user);

    get(route('garage.shelves.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Shelves/Index')
            ->has('shelves', 3)
            ->where('shelves.0', ['id' => $first->id, 'name' => 'Électroportatif', 'position' => 1])
            ->where('shelves.1', ['id' => $second->id, 'name' => 'Manuel', 'position' => 2])
            ->where('shelves.2', ['id' => $third->id, 'name' => 'Jardin', 'position' => 3]));
});

it('renders an empty garage', function () {
    actingAs(User::factory()->create());

    get(route('garage.shelves.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Garage/Shelves/Index')->has('shelves', 0));
});

it('never lists the bookshelves of the library', function () {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    actingAs($user);

    get(route('garage.shelves.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Garage/Shelves/Index')->has('shelves', 0));
});

it('opens the garage on the tools of the member', function (bool $admin) {
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    get(route('garage.index'))->assertRedirect(route('garage.tools.index'));
})->with(['user' => [false], 'admin' => [true]]);
