<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolPhoto;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

/**
 * A tool of the signed-in user: brand Bosch, accessories, photos a, b, c.
 */
function signedInToolWithPhotos(): Tool
{
    $tool = toolWithPhotos(signIn(), ['a', 'b', 'c']);
    $tool->brand()->associate(Brand::factory()->create(['name' => 'Bosch']))->save();
    $tool->accessories()->createMany([
        ['name' => 'Mallette', 'position' => 1],
        ['name' => 'Batterie', 'position' => 2],
    ]);

    return $tool;
}

/**
 * @return array{id: int}
 */
function keptPhoto(Tool $tool, string $name): array
{
    return ['id' => $tool->photos()->where('path', "tools/{$name}.jpg")->sole()->id];
}

it('updates the name, brand, shelves and accessories, keeping the photos', function () {
    $tool = signedInToolWithPhotos();
    $kept = ToolShelf::factory()->for($tool->user)->create(['name' => 'Établi']);
    $removed = ToolShelf::factory()->for($tool->user)->create(['name' => 'Jardin']);
    $added = ToolShelf::factory()->for($tool->user)->create(['name' => 'Électroportatif']);
    $tool->shelves()->attach([$kept->id, $removed->id]);

    patch(route('garage.tools.update', $tool), [
        'name' => ' Perceuse à percussion ',
        'brand' => 'Makita',
        'shelf_ids' => [$kept->id, $added->id],
        'accessories' => ['Batterie', 'Chargeur', 'Mallette'],
        'photos' => [keptPhoto($tool, 'a'), keptPhoto($tool, 'b'), keptPhoto($tool, 'c')],
    ])
        ->assertRedirect(route('garage.tools.show', $tool))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'L’outil « Perceuse à percussion » a été modifié.']);

    $tool->refresh();
    expect($tool->name)->toBe('Perceuse à percussion')
        ->and($tool->brand?->name)->toBe('Makita')
        ->and($tool->shelves->pluck('name')->all())->toBe(['Établi', 'Électroportatif'])
        ->and(toolAccessoryNames($tool))->toBe(['Batterie', 'Chargeur', 'Mallette'])
        ->and(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(Brand::query()->orderBy('id')->pluck('name')->all())->toBe(['Bosch', 'Makita']);
});

it('removes the brand, accessories, shelves and photos when none is sent, but never the brand itself', function () {
    $tool = signedInToolWithPhotos();
    $tool->shelves()->attach(ToolShelf::factory()->for($tool->user)->create());

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'brand' => null])->assertSessionHasNoErrors();

    $tool->refresh();
    expect($tool->brand_id)->toBeNull()
        ->and($tool->shelves)->toBeEmpty()
        ->and(toolAccessoryNames($tool))->toBe([])
        ->and(toolPhotoPaths($tool))->toBe([])
        ->and(ToolPhoto::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(Brand::query()->pluck('name')->all())->toBe(['Bosch']);
});

it('reorders, removes and adds photos in one go, the first one becoming the thumbnail', function () {
    $tool = signedInToolWithPhotos();

    // A file goes with POST + _method=patch, as the browser sends it.
    post(route('garage.tools.update', $tool), [
        '_method' => 'patch',
        'name' => 'Perceuse',
        'photos' => [
            keptPhoto($tool, 'c'),
            ['file' => UploadedFile::fake()->image('nouvelle.jpg', 2400, 1600)],
            keptPhoto($tool, 'a'),
        ],
    ])->assertSessionHasNoErrors();

    $paths = toolPhotoPaths($tool);
    expect($paths)->toHaveCount(3)
        ->and($paths[0])->toBe('tools/c.jpg')
        ->and($paths[1])->toMatch('#^tools/[A-Za-z0-9]{40}\.jpg$#')
        ->and($paths[2])->toBe('tools/a.jpg')
        ->and($tool->photos()->pluck('position')->all())->toBe([1, 2, 3])
        ->and($tool->thumbnail?->path)->toBe('tools/c.jpg')
        ->and(storedImage($paths[1]))->toBe([1200, 800, 'mime' => 'image/jpeg'])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing($paths);
    Storage::disk('public')->assertMissing('tools/b.jpg');
});

it('reorders the accessories', function () {
    $tool = signedInToolWithPhotos();

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'accessories' => ['Batterie', 'Mallette']])
        ->assertSessionHasNoErrors();

    expect(toolAccessoryNames($tool))->toBe(['Batterie', 'Mallette']);
});

it('refuses a photo of another tool, even one of the user, and changes nothing', function (Closure $otherTool) {
    $tool = signedInToolWithPhotos();
    $other = $otherTool($tool->user);

    patch(route('garage.tools.update', $tool), [
        'name' => 'Modifié',
        'photos' => [keptPhoto($tool, 'a'), ['id' => $other->photos()->sole()->id]],
    ])->assertSessionHasErrors(['photos.1.id' => 'Une des photos n’appartient pas à cet outil.']);

    expect($tool->fresh()?->name)->toBe('Perceuse')
        ->and(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(toolPhotoPaths($other))->toBe(['tools/other.jpg']);
    Storage::disk('public')->assertExists('tools/other.jpg');
})->with([
    'tool of another user' => fn (User $user) => toolWithPhotos(User::factory()->create(), ['other'], 'Scie'),
    'another tool of the user' => fn (User $user) => toolWithPhotos($user, ['other'], 'Scie'),
]);

it('refuses the same photo twice and a photo with both an id and a file', function (Closure $photos, string $error) {
    $tool = signedInToolWithPhotos();

    patch(route('garage.tools.update', $tool), ['name' => 'Perceuse', 'photos' => $photos($tool)])
        ->assertSessionHasErrors([$error]);

    expect(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(Storage::disk('public')->allFiles())->toHaveCount(3);
})->with([
    'twice' => [fn (Tool $tool) => [keptPhoto($tool, 'a'), keptPhoto($tool, 'a')], 'photos.0.id'],
    'id and file' => [fn (Tool $tool) => [[...keptPhoto($tool, 'a'), 'file' => UploadedFile::fake()->image('x.jpg', 10, 10)]], 'photos.0.id'],
]);

it('counts kept and new photos together against the maximum', function () {
    $tool = signedInToolWithPhotos();
    $newPhotos = array_map(fn (int $index) => ['file' => UploadedFile::fake()->image("{$index}.jpg", 10, 10)], range(1, 6));

    patch(route('garage.tools.update', $tool), [
        'name' => 'Perceuse',
        'photos' => [keptPhoto($tool, 'a'), keptPhoto($tool, 'b'), keptPhoto($tool, 'c'), ...$newPhotos],
    ])->assertSessionHasErrors(['photos']);

    expect(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(Storage::disk('public')->allFiles())->toHaveCount(3);
});

it('refuses shelves of another user and changes nothing', function () {
    $tool = signedInToolWithPhotos();
    $own = ToolShelf::factory()->for($tool->user)->create();
    $tool->shelves()->attach($own);

    patch(route('garage.tools.update', $tool), [
        'name' => 'Perceuse',
        'shelf_ids' => [ToolShelf::factory()->create()->id],
    ])->assertSessionHasErrors(['shelf_ids.0']);

    expect($tool->shelves()->pluck('tool_shelves.id')->all())->toBe([$own->id]);
});

it('keeps the previous photos and removes the new files when saving the tool fails', function () {
    $tool = signedInToolWithPhotos();
    Tool::saving(fn () => throw new RuntimeException('Database unavailable'));

    patch(route('garage.tools.update', $tool), [
        'name' => 'Perceuse modifiée',
        'photos' => [keptPhoto($tool, 'b'), ['file' => UploadedFile::fake()->image('new.jpg', 100, 100)]],
    ])->assertServerError();

    expect($tool->fresh()?->name)->toBe('Perceuse')
        ->and(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg']);
});

it('keeps the files of the removed photos when the photos cannot be written', function () {
    $tool = signedInToolWithPhotos();
    ToolPhoto::creating(fn () => throw new RuntimeException('Database unavailable'));

    patch(route('garage.tools.update', $tool), [
        'name' => 'Perceuse',
        'photos' => [keptPhoto($tool, 'b'), ['file' => UploadedFile::fake()->image('new.jpg', 100, 100)]],
    ])->assertServerError();

    expect(toolPhotoPaths($tool))->toBe(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg'])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(['tools/a.jpg', 'tools/b.jpg', 'tools/c.jpg']);
});
