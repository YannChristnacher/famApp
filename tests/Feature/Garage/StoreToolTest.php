<?php

use App\Http\Requests\Garage\ToolRequest;
use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\Garage\ToolPhoto;
use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\post;

beforeEach(fn () => Storage::fake('public'));

it('creates a tool with a new brand, accessories, shelves and resized photos, in order', function () {
    $user = signIn();
    $power = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $workbench = ToolShelf::factory()->for($user)->create(['name' => 'Établi']);
    ToolShelf::factory()->for($user)->create(['name' => 'Jardin']);

    $response = post(route('garage.tools.store'), [
        'name' => '  Perceuse visseuse ',
        'brand' => ' Bosch ',
        'accessories' => [' Mallette ', 'Batterie 18 V', 'Chargeur'],
        'shelf_ids' => [$workbench->id, $power->id],
        'photos' => [
            ['file' => UploadedFile::fake()->image('face.jpg', 3000, 2000)],
            ['file' => UploadedFile::fake()->image('cote.png', 600, 900)],
        ],
    ]);

    $tool = Tool::query()->sole();
    $response->assertRedirect(route('garage.tools.show', $tool))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'L’outil « Perceuse visseuse » a été ajouté.']);

    $photos = toolPhotoPaths($tool);
    expect($tool->user_id)->toBe($user->id)
        ->and($tool->name)->toBe('Perceuse visseuse')
        ->and($tool->brand?->name)->toBe('Bosch')
        ->and($tool->shelves->pluck('name')->all())->toBe(['Électroportatif', 'Établi'])
        ->and(toolAccessoryNames($tool))->toBe(['Mallette', 'Batterie 18 V', 'Chargeur'])
        ->and($tool->photos->pluck('position')->all())->toBe([1, 2])
        ->and($photos)->toHaveCount(2)
        ->and($photos)->each->toMatch('#^tools/[A-Za-z0-9]{40}\.jpg$#')
        ->and(storedImage($photos[0]))->toBe([1200, 800, 'mime' => 'image/jpeg'])
        ->and(storedImage($photos[1]))->toBe([600, 900, 'mime' => 'image/jpeg'])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing($photos);
});

it('creates a tool with a name only', function () {
    $user = signIn();

    post(route('garage.tools.store'), ['name' => 'Marteau', 'brand' => ''])->assertSessionHasNoErrors();

    $tool = Tool::query()->sole();
    expect($tool->user_id)->toBe($user->id)
        ->and($tool->brand_id)->toBeNull()
        ->and($tool->shelves)->toBeEmpty()
        ->and($tool->accessories)->toBeEmpty()
        ->and($tool->photos)->toBeEmpty()
        ->and(Brand::query()->count())->toBe(0);
});

it('reuses an existing brand whatever its case and spaces', function (string $typed) {
    signIn();
    $bosch = Brand::factory()->create(['name' => 'Bosch']);

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'brand' => $typed])->assertSessionHasNoErrors();

    expect(Tool::query()->sole()->brand_id)->toBe($bosch->id)
        ->and(Brand::query()->pluck('name')->all())->toBe(['Bosch']);
})->with(['same' => 'Bosch', 'lower case' => 'bosch', 'upper case with spaces' => '  BOSCH ']);

it('shares the brands between members: a brand created by one is reused by another', function () {
    signIn();
    post(route('garage.tools.store'), ['name' => 'Perceuse', 'brand' => 'Black  & Decker']);
    signIn();
    post(route('garage.tools.store'), ['name' => 'Ponceuse', 'brand' => 'black & decker']);

    expect(Brand::query()->pluck('name')->all())->toBe(['Black  & Decker'])
        ->and(Tool::query()->distinct()->pluck('brand_id')->all())->toHaveCount(1);
});

it('keeps accents in brands: two names differing by an accent are two brands', function () {
    signIn();
    post(route('garage.tools.store'), ['name' => 'Scie', 'brand' => 'Leroy']);
    post(route('garage.tools.store'), ['name' => 'Rabot', 'brand' => 'Leröy']);

    expect(Brand::query()->orderBy('id')->pluck('name')->all())->toBe(['Leroy', 'Leröy']);
});

it('creates the tool for the signed-in user, ignoring any user or brand id sent', function () {
    $user = signIn();
    $other = User::factory()->create();
    $bosch = Brand::factory()->create(['name' => 'Bosch']);

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'user_id' => $other->id, 'brand_id' => $bosch->id])
        ->assertSessionHasNoErrors();

    expect(Tool::query()->sole()->user_id)->toBe($user->id)
        ->and(Tool::query()->sole()->brand_id)->toBeNull();
});

it('refuses shelves of another user or of the library, and writes nothing', function (Closure $shelf) {
    $user = signIn();

    post(route('garage.tools.store'), [
        'name' => 'Perceuse',
        'shelf_ids' => [ToolShelf::factory()->for($user)->create()->id, $shelf($user)],
        'photos' => [['file' => UploadedFile::fake()->image('face.jpg', 100, 100)]],
    ])->assertSessionHasErrors(['shelf_ids.1' => 'Une des étagères choisies n’existe pas.']);

    expect(Tool::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'another user' => fn (User $user) => ToolShelf::factory()->create()->id,
    'library bookshelf of the user' => fn (User $user) => Bookshelf::factory()->for($user)->create()->id,
    'unknown' => fn (User $user) => 999,
]);

it('validates the tool', function (array $data, array $errors) {
    signIn();

    post(route('garage.tools.store'), ['name' => 'Perceuse', ...$data])->assertSessionHasErrors($errors);

    expect(Tool::query()->count())->toBe(0)
        ->and(Brand::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'name missing' => [['name' => '  '], ['name']],
    'name too long' => [['name' => Str::repeat('a', ToolRequest::NAME_MAX_LENGTH + 1)], ['name']],
    'brand too long' => [['brand' => Str::repeat('a', ToolRequest::BRAND_MAX_LENGTH + 1)], ['brand']],
    'brand not a string' => [['brand' => ['Bosch']], ['brand']],
    'empty accessory' => [['accessories' => ['Mallette', '  ']], ['accessories.1']],
    'accessory too long' => [['accessories' => [Str::repeat('a', ToolRequest::ACCESSORY_MAX_LENGTH + 1)]], ['accessories.0']],
    'accessory with an id' => [['accessories' => [['id' => 1, 'name' => 'Lame']]], ['accessories.0']],
    'too many accessories' => [['accessories' => array_fill(0, ToolRequest::MAX_ACCESSORIES + 1, 'Lame')], ['accessories']],
    'accessories not a list' => [['accessories' => 'Lame'], ['accessories']],
    'shelves not a list' => [['shelf_ids' => 3], ['shelf_ids']],
    'photos not a list' => [['photos' => 'photo'], ['photos']],
    'photo without file' => [['photos' => [[]]], ['photos.0']],
]);

it('validates each photo by its content, size and dimensions', function (Closure $file, string $message) {
    signIn();

    post(route('garage.tools.store'), [
        'name' => 'Perceuse',
        'photos' => [['file' => UploadedFile::fake()->image('ok.jpg', 100, 100)], ['file' => $file()]],
    ])->assertSessionHasErrors(['photos.1.file' => $message]);

    expect(Tool::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'not an image' => [fn () => UploadedFile::fake()->createWithContent('photo.jpg', 'not an image'), 'Le fichier de la photo doit être une image.'],
    'GIF' => [fn () => UploadedFile::fake()->image('photo.gif', 100, 100), 'Le champ photo doit être un fichier de type : jpeg, png, webp.'],
    'too heavy' => [fn () => UploadedFile::fake()->image('photo.jpg', 100, 100)->size(10 * 1024 + 1), 'La taille du fichier de photo ne peut pas dépasser 10240 kilo-octets.'],
    'too large' => [fn () => UploadedFile::fake()->image('photo.jpg', 6001, 10), 'La photo ne doit pas dépasser 6000 pixels de côté.'],
]);

it('refuses more than the maximum number of photos', function () {
    signIn();
    $photos = array_map(
        fn (int $index) => ['file' => UploadedFile::fake()->image("photo-{$index}.jpg", 10, 10)],
        range(1, ToolRequest::MAX_PHOTOS + 1),
    );

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'photos' => $photos])
        ->assertSessionHasErrors(['photos' => 'Un outil ne peut pas avoir plus de 8 photos.']);

    expect(Tool::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('accepts exactly the maximum number of photos', function () {
    signIn();
    $photos = array_map(
        fn (int $index) => ['file' => UploadedFile::fake()->image("photo-{$index}.jpg", 10, 10)],
        range(1, ToolRequest::MAX_PHOTOS),
    );

    post(route('garage.tools.store'), ['name' => 'Perceuse', 'photos' => $photos])->assertSessionHasNoErrors();

    expect(ToolPhoto::query()->count())->toBe(ToolRequest::MAX_PHOTOS);
});

it('refuses an existing photo id at creation, even one of the user', function () {
    $user = signIn();
    $other = toolWithPhotos($user, ['mine']);
    $photoId = $other->photos()->sole()->id;

    post(route('garage.tools.store'), ['name' => 'Ponceuse', 'photos' => [['id' => $photoId]]])
        ->assertSessionHasErrors(['photos.0.id']);

    expect(Tool::query()->count())->toBe(1)
        ->and(toolPhotoPaths($other))->toBe(['tools/mine.jpg']);
});

it('removes the stored photos when the tool cannot be saved', function () {
    signIn();
    Tool::saving(fn () => throw new RuntimeException('Database unavailable.'));

    post(route('garage.tools.store'), [
        'name' => 'Perceuse',
        'photos' => [
            ['file' => UploadedFile::fake()->image('a.jpg', 100, 100)],
            ['file' => UploadedFile::fake()->image('b.jpg', 100, 100)],
        ],
    ])->assertServerError();

    expect(Tool::query()->count())->toBe(0)
        ->and(ToolPhoto::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
