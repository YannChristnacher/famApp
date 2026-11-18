<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolAccessory;
use App\Models\Garage\ToolPhoto;
use App\Models\Garage\ToolShelf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\delete;

beforeEach(fn () => Storage::fake('public'));

it('deletes the tool with its photos, accessories and shelf links, keeping shelves and brand', function () {
    $user = signIn();
    $tool = toolWithPhotos($user, ['a', 'b']);
    $bosch = Brand::factory()->create(['name' => 'Bosch']);
    $tool->brand()->associate($bosch)->save();
    $tool->accessories()->create(['name' => 'Mallette', 'position' => 1]);
    $shelf = ToolShelf::factory()->for($user)->create();
    $tool->shelves()->attach($shelf);
    $kept = toolWithPhotos($user, ['kept'], 'Scie');
    $kept->shelves()->attach($shelf);

    delete(route('garage.tools.destroy', $tool))
        ->assertRedirect(route('garage.tools.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'L’outil « Perceuse » a été supprimé.']);

    expect(Tool::query()->pluck('name')->all())->toBe(['Scie'])
        ->and(ToolPhoto::query()->pluck('path')->all())->toBe(['tools/kept.jpg'])
        ->and(ToolAccessory::query()->count())->toBe(0)
        ->and(DB::table('tool_tool_shelf')->pluck('tool_id')->all())->toBe([$kept->id])
        ->and($shelf->fresh())->not->toBeNull()
        ->and($bosch->fresh())->not->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe(['tools/kept.jpg']);
});

it('locks the tool before reading the paths of its photos, in one transaction', function () {
    $tool = toolWithPhotos(signIn(), ['a']);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        // Without identifier quotes: double quotes on SQLite, backticks on MySQL.
        $queries[] = str_replace(['"', '`'], '', $query->sql);
    });

    delete(route('garage.tools.destroy', $tool))->assertRedirect();

    $lock = collect($queries)->search(fn (string $sql) => str_starts_with($sql, 'select * from tools where tools.id = ?')
        && (DB::getDriverName() !== 'mysql' || str_contains($sql, 'for update')));
    $paths = collect($queries)->search(fn (string $sql) => str_starts_with($sql, 'select path from tool_photos'));
    expect($lock)->toBeInt()
        ->and($paths)->toBeInt()
        ->and($lock)->toBeLessThan($paths);
});

it('keeps the photos, rows and files, when the tool cannot be deleted', function () {
    $tool = toolWithPhotos(signIn(), ['a', 'b']);
    $tool->accessories()->create(['name' => 'Mallette', 'position' => 1]);
    Tool::deleted(fn () => throw new RuntimeException('Database unavailable'));

    delete(route('garage.tools.destroy', $tool))->assertServerError();

    expect($tool->fresh())->not->toBeNull()
        ->and(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg'])
        ->and(toolAccessoryNames($tool))->toBe(['Mallette'])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(['tools/a.jpg', 'tools/b.jpg']);
});

it('deletes a shelf without deleting its tools', function () {
    $user = signIn();
    $tool = Tool::factory()->for($user)->create();
    $shelf = ToolShelf::factory()->for($user)->create();
    $tool->shelves()->attach($shelf);

    delete(route('garage.shelves.destroy', $shelf))->assertRedirect();

    expect($tool->fresh())->not->toBeNull()
        ->and($tool->shelves()->count())->toBe(0);
});
