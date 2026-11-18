<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('public'));

it('opens the garage on the tools of the member', function () {
    actingAs(User::factory()->create());

    get(route('garage.index'))->assertRedirect(route('garage.tools.index'));
});

it('lists only the tools of the signed-in user, by name, with thumbnail, brand and shelves', function () {
    $user = User::factory()->create();
    $power = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $workbench = ToolShelf::factory()->for($user)->create(['name' => 'Établi']);
    $bosch = Brand::factory()->create(['name' => 'Bosch']);
    $drill = toolWithPhotos($user, ['front', 'side'], 'Perceuse');
    $drill->brand()->associate($bosch)->save();
    $drill->shelves()->attach([$workbench->id, $power->id]);
    $hammer = Tool::factory()->for($user)->create(['name' => 'Marteau']);
    Tool::factory()->create(['name' => 'La scie de Paul']);
    actingAs($user);

    get(route('garage.tools.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage/Tools/Index')
            ->has('tools', 2)
            ->where('tools.0', [
                'id' => $hammer->id,
                'name' => 'Marteau',
                'brand' => null,
                'thumbnail_url' => null,
                'shelves' => [],
            ])
            ->where('tools.1', [
                'id' => $drill->id,
                'name' => 'Perceuse',
                'brand' => ['id' => $bosch->id, 'name' => 'Bosch'],
                'thumbnail_url' => Storage::disk('public')->url('tools/front.jpg'),
                'shelves' => [
                    ['id' => $power->id, 'name' => 'Électroportatif'],
                    ['id' => $workbench->id, 'name' => 'Établi'],
                ],
            ])
            ->where('shelves', [
                ['id' => $power->id, 'name' => 'Électroportatif', 'position' => 1],
                ['id' => $workbench->id, 'name' => 'Établi', 'position' => 2],
            ])
            ->where('brands', [['id' => $bosch->id, 'name' => 'Bosch']])
            ->where('filters', ['shelf' => null, 'brand' => null]));
});

it('uses the first photo by position as the thumbnail', function () {
    $user = User::factory()->create();
    $tool = toolWithPhotos($user, ['second', 'first']);
    $tool->photos()->where('path', 'tools/first.jpg')->update(['position' => 0]);
    actingAs($user);

    get(route('garage.tools.index'))
        ->assertInertia(fn (Assert $page) => $page->where('tools.0.thumbnail_url', Storage::disk('public')->url('tools/first.jpg')));
});

it('offers to filter only by the brands of the tools of the member, by name', function () {
    $user = User::factory()->create();
    $makita = Brand::factory()->create(['name' => 'Makita']);
    $bosch = Brand::factory()->create(['name' => 'Bosch']);
    Brand::factory()->create(['name' => 'Festool']);
    Tool::factory()->for($user)->for($makita)->create();
    Tool::factory()->for($user)->for($bosch)->count(2)->create();
    Tool::factory()->for(Brand::factory()->create(['name' => 'Stanley']))->create();
    actingAs($user);

    get(route('garage.tools.index'))
        ->assertInertia(fn (Assert $page) => $page->where('brands', [
            ['id' => $bosch->id, 'name' => 'Bosch'],
            ['id' => $makita->id, 'name' => 'Makita'],
        ]));
});

it('filters the tools by shelf, by brand, or by both', function () {
    $user = User::factory()->create();
    $power = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $bosch = Brand::factory()->create(['name' => 'Bosch']);
    $makita = Brand::factory()->create(['name' => 'Makita']);
    Tool::factory()->for($user)->for($bosch)->create(['name' => 'Perceuse'])->shelves()->attach($power);
    Tool::factory()->for($user)->for($makita)->create(['name' => 'Meuleuse'])->shelves()->attach($power);
    Tool::factory()->for($user)->for($bosch)->create(['name' => 'Ponceuse']);
    Tool::factory()->for($user)->create(['name' => 'Marteau']);
    actingAs($user);

    $names = fn (array $query) => get(route('garage.tools.index', $query))->assertOk()->viewData('page')['props']['tools'];

    expect(array_column($names(['shelf' => $power->id]), 'name'))->toBe(['Meuleuse', 'Perceuse'])
        ->and(array_column($names(['brand' => $bosch->id]), 'name'))->toBe(['Perceuse', 'Ponceuse'])
        ->and(array_column($names(['shelf' => $power->id, 'brand' => $bosch->id]), 'name'))->toBe(['Perceuse']);

    get(route('garage.tools.index', ['shelf' => $power->id, 'brand' => $bosch->id]))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['shelf' => $power->id, 'brand' => $bosch->id]));
});

it('answers 404 when filtering by a shelf that is not one of the user', function (Closure $shelf) {
    actingAs(User::factory()->create());

    get(route('garage.tools.index', ['shelf' => $shelf()]))->assertNotFound();
})->with([
    'another user' => fn () => ToolShelf::factory()->create()->id,
    'a library bookshelf of the user' => fn () => Bookshelf::factory()->for(auth()->user())->create()->id,
    'unknown' => fn () => 999,
    'not an integer' => fn () => 'atelier',
    'array' => fn () => [1],
]);

it('answers 404 when filtering by a brand none of the tools of the user has', function (Closure $brand) {
    actingAs(User::factory()->create());

    get(route('garage.tools.index', ['brand' => $brand()]))->assertNotFound();
})->with([
    'brand of another user only' => fn () => Tool::factory()->for(Brand::factory()->create())->create()->brand_id,
    'unused brand' => fn () => Brand::factory()->create()->id,
    'unknown' => fn () => 999,
    'not an integer' => fn () => 'bosch',
    'array' => fn () => [1],
]);

it('loads the list with the same number of queries whatever the number of tools', function () {
    $countQueries = function (int $tools): int {
        $user = User::factory()->create();
        $shelves = collect([ToolShelf::factory()->for($user)->create(), ToolShelf::factory()->for($user)->create()]);
        Tool::factory()->for($user)->count($tools)->create()->each(function (Tool $tool, int $index) use ($shelves) {
            $tool->brand()->associate(Brand::factory()->create())->save();
            $tool->shelves()->attach($shelves);
            $tool->photos()->create(['path' => "tools/{$index}-a.jpg", 'position' => 1]);
            $tool->photos()->create(['path' => "tools/{$index}-b.jpg", 'position' => 2]);
        });
        actingAs($user);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        get(route('garage.tools.index', ['shelf' => $shelves->first()?->id]))->assertOk();

        return $queries;
    };

    $withOneTool = $countQueries(1);
    expect($countQueries(5))->toBe($withOneTool);
});
