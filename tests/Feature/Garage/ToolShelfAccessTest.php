<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(ToolShelf): TestResponse<Response>>
 */
function toolShelfCalls(): array
{
    return [
        'garage.index' => fn (ToolShelf $shelf) => get(route('garage.index')),
        'index' => fn (ToolShelf $shelf) => get(route('garage.shelves.index')),
        'store' => fn (ToolShelf $shelf) => post(route('garage.shelves.store'), ['name' => 'Manuel']),
        'update' => fn (ToolShelf $shelf) => patch(route('garage.shelves.update', $shelf), ['name' => 'Manuel']),
        'destroy' => fn (ToolShelf $shelf) => delete(route('garage.shelves.destroy', $shelf)),
        'move' => fn (ToolShelf $shelf) => patch(route('garage.shelves.move', $shelf), ['direction' => 'up']),
    ];
}

it('redirects a guest to the login page on every garage route and changes nothing', function (string $call) {
    $owner = User::factory()->create();
    ToolShelf::factory()->for($owner)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($owner)->create(['name' => 'Jardin']);

    toolShelfCalls()[$call]($shelf)->assertRedirect(route('login'));

    expect(toolShelfNames($owner))->toBe(['Électroportatif', 'Jardin'])
        ->and(ToolShelf::query()->count())->toBe(2);
})->with(array_keys(toolShelfCalls()));

it('hides the tool shelves of another user on every route that takes one', function (string $call) {
    $owner = User::factory()->create();
    ToolShelf::factory()->for($owner)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($owner)->create(['name' => 'Jardin']);
    actingAs(User::factory()->create());

    toolShelfCalls()[$call]($shelf)->assertNotFound();

    expect(toolShelfNames($owner))->toBe(['Électroportatif', 'Jardin']);
})->with(['update', 'destroy', 'move']);

it('hides the tool shelves of another user from an admin too', function (string $call) {
    $owner = User::factory()->create();
    $shelf = ToolShelf::factory()->for($owner)->create(['name' => 'Électroportatif']);
    actingAs(User::factory()->admin()->create());

    toolShelfCalls()[$call]($shelf)->assertNotFound();

    expect(toolShelfNames($owner))->toBe(['Électroportatif']);
})->with(['update', 'destroy', 'move']);

it('lets a signed-in user reach every garage route on their own tool shelf', function (string $call, int $status, ?string $redirect) {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Jardin']);
    actingAs($user);

    $response = toolShelfCalls()[$call]($shelf)->assertStatus($status);

    if ($redirect !== null) {
        $response->assertRedirect(route($redirect))->assertSessionHasNoErrors();
    }
})->with([
    'garage.index' => ['garage.index', 302, 'garage.tools.index'],
    'index' => ['index', 200, null],
    'store' => ['store', 302, 'garage.shelves.index'],
    'update' => ['update', 302, 'garage.shelves.index'],
    'destroy' => ['destroy', 302, 'garage.shelves.index'],
    'move' => ['move', 302, 'garage.shelves.index'],
]);

it('answers 404 for a tool shelf that does not exist', function (string $call) {
    actingAs(User::factory()->create());
    $missing = ToolShelf::factory()->make(['id' => 999]);

    toolShelfCalls()[$call]($missing)->assertNotFound();
})->with(['update', 'destroy', 'move']);
