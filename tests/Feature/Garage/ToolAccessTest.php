<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

/**
 * @return array<string, Closure(Tool): TestResponse<Response>>
 */
function toolCalls(): array
{
    return [
        'index' => fn (Tool $tool) => get(route('garage.tools.index')),
        'create' => fn (Tool $tool) => get(route('garage.tools.create')),
        'store' => fn (Tool $tool) => post(route('garage.tools.store'), ['name' => 'Scie sauteuse']),
        'show' => fn (Tool $tool) => get(route('garage.tools.show', $tool)),
        'edit' => fn (Tool $tool) => get(route('garage.tools.edit', $tool)),
        'update' => fn (Tool $tool) => patch(route('garage.tools.update', $tool), [
            'name' => 'Scie sauteuse',
            'brand' => 'Makita',
            'accessories' => ['Lame'],
            'photos' => [['file' => UploadedFile::fake()->image('photo.jpg', 100, 100)]],
        ]),
        'destroy' => fn (Tool $tool) => delete(route('garage.tools.destroy', $tool)),
    ];
}

/**
 * A tool with a brand, an accessory, a photo and a shelf, owned by a new user.
 */
function ownedToolWithEverything(): Tool
{
    Storage::fake('public');
    $tool = toolWithPhotos(User::factory()->create(), ['original']);
    $tool->brand()->associate(Brand::factory()->create(['name' => 'Bosch']))->save();
    $tool->accessories()->create(['name' => 'Mallette', 'position' => 1]);
    $tool->shelves()->attach(ToolShelf::factory()->for($tool->user)->create());

    return $tool;
}

function expectToolUnchanged(Tool $tool): void
{
    $fresh = $tool->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh?->name)->toBe('Perceuse')
        ->and($fresh?->brand?->name)->toBe('Bosch')
        ->and($fresh?->shelves()->count())->toBe(1)
        ->and(toolAccessoryNames($tool))->toBe(['Mallette'])
        ->and(toolPhotoPaths($tool))->toBe(['tools/original.jpg'])
        ->and(Tool::query()->count())->toBe(1)
        ->and(Brand::query()->pluck('name')->all())->toBe(['Bosch'])
        ->and(Storage::disk('public')->allFiles())->toBe(['tools/original.jpg']);
}

it('redirects a guest to the login page on every tool route and changes nothing', function (string $call) {
    $tool = ownedToolWithEverything();

    toolCalls()[$call]($tool)->assertRedirect(route('login'));

    expectToolUnchanged($tool);
})->with(array_keys(toolCalls()));

it('hides the tools of another user on every route that takes one', function (string $call, bool $admin) {
    $tool = ownedToolWithEverything();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    toolCalls()[$call]($tool)->assertNotFound();

    expectToolUnchanged($tool);
})->with(['edit', 'update', 'destroy'])->with(['user' => [false], 'admin' => [true]]);

// Since F11 every member reads the tools of the others (read-only): the page
// opens, without any right to change the tool.
it('lets another user or an admin read the page of a tool without changing it', function (bool $admin) {
    $tool = ownedToolWithEverything();
    actingAs($admin ? User::factory()->admin()->create() : User::factory()->create());

    toolCalls()['show']($tool)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Tools/Show')
            ->where('tool.id', $tool->id)
            ->where('editable', false));

    expectToolUnchanged($tool);
})->with(['user' => [false], 'admin' => [true]]);

it('lets the owner reach every tool route on their own tool', function (string $call, int $status) {
    $tool = ownedToolWithEverything();
    actingAs($tool->user);

    toolCalls()[$call]($tool)->assertStatus($status)->assertSessionHasNoErrors();
})->with([
    'index' => ['index', 200],
    'create' => ['create', 200],
    'store' => ['store', 302],
    'show' => ['show', 200],
    'edit' => ['edit', 200],
    'update' => ['update', 302],
    'destroy' => ['destroy', 302],
]);

it('answers 404 for a tool that does not exist', function () {
    actingAs(User::factory()->create());

    get(route('garage.tools.show', 999))->assertNotFound();
});
