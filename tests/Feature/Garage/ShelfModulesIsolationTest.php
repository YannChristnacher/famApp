<?php

use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\patch;

/**
 * Calls of the garage and library routes that take a shelf, by raw id.
 *
 * @return array<string, Closure(int): TestResponse<Response>>
 */
function shelfCallsById(string $module): array
{
    $prefix = $module === 'garage' ? 'garage.shelves' : 'library.bookshelves';

    return [
        'update' => fn (int $id) => patch(route("{$prefix}.update", $id), ['name' => 'Renommée']),
        'destroy' => fn (int $id) => delete(route("{$prefix}.destroy", $id)),
        'move' => fn (int $id) => patch(route("{$prefix}.move", $id), ['direction' => 'up']),
    ];
}

it('never reaches a bookshelf of the library through the garage routes', function (string $call) {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    actingAs($user);

    expect(ToolShelf::query()->find($bookshelf->id))->toBeNull();

    shelfCallsById('garage')[$call]($bookshelf->id)->assertNotFound();

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars']);
})->with(['update', 'destroy', 'move']);

it('never reaches a tool shelf of the garage through the library routes', function (string $call) {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    actingAs($user);

    expect(Bookshelf::query()->find($shelf->id))->toBeNull();

    shelfCallsById('library')[$call]($shelf->id)->assertNotFound();

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel']);
})->with(['update', 'destroy', 'move']);
