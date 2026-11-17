<?php

use App\Models\Garage\ToolShelf;
use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Services\Garage\ToolShelfService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\post;

it('stores the normalized name next to the name', function (string $name, string $normalized) {
    $shelf = ToolShelf::factory()->create(['name' => $name]);

    expect($shelf->fresh()?->normalized_name)->toBe($normalized)
        ->and(ToolShelf::normalizeName($name))->toBe($normalized);
})->with([
    'case' => ['Clés à Œil', 'clés à œil'],
    'accented capitals' => ['ÉTÉ', 'été'],
    'inner spaces' => ["Outils   à main\t", 'outils à main'],
    'accents kept' => ['Café', 'café'],
]);

it('updates the normalized name on rename', function () {
    $shelf = ToolShelf::factory()->create(['name' => 'Manuel']);

    $shelf->update(['name' => 'Outils  À MAIN']);

    expect($shelf->fresh()?->normalized_name)->toBe('outils à main');
});

it('refuses in the database a name differing only by case or inner spaces, even without the validation rule', function (string $duplicate) {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Clés à œil']);

    expect(fn () => ToolShelf::factory()->for($user)->create(['name' => $duplicate]))
        ->toThrow(UniqueConstraintViolationException::class);
})->with(['CLÉS À ŒIL', 'clés  à œil']);

it('reports a duplicate caught by the database as a validation error', function () {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $other = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);

    expect(fn () => app(ToolShelfService::class)->create($user, 'ÉLECTROPORTATIF'))->toThrow(ValidationException::class)
        ->and(fn () => app(ToolShelfService::class)->rename($other, 'électroportatif'))->toThrow(ValidationException::class);

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel']);
});

it('treats accented and unaccented names as different', function () {
    $user = signIn();

    post(route('garage.shelves.store'), ['name' => 'Café'])->assertSessionHasNoErrors();
    post(route('garage.shelves.store'), ['name' => 'Cafe'])->assertSessionHasNoErrors();
    post(route('garage.shelves.store'), ['name' => 'Été'])->assertSessionHasNoErrors();
    post(route('garage.shelves.store'), ['name' => 'Ete'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Café', 'Cafe', 'Été', 'Ete']);
});

it('rejects with the validation rule a name differing only by case, accented capitals or inner spaces', function (string $duplicate) {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Été en  pente douce']);

    post(route('garage.shelves.store'), ['name' => $duplicate])
        ->assertSessionHasErrors(['name' => 'Vous avez déjà une étagère nommée « Été en  pente douce ».']);

    expect(toolShelfNames($user))->toBe(['Été en  pente douce']);
})->with(['ÉTÉ EN PENTE DOUCE', ' été en pente   douce ']);

it('refuses two tool shelves of a member in the same position', function () {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif', 'position' => 1]);

    expect(fn () => ToolShelf::factory()->for($user)->create(['name' => 'Manuel', 'position' => 1]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('accepts the name of a bookshelf of the library of the member', function () {
    $user = signIn();
    Bookshelf::factory()->for($user)->create(['name' => 'Électroportatif']);

    post(route('garage.shelves.store'), ['name' => 'électroportatif'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['électroportatif']);
});

it('compares the normalized names byte for byte under MySQL', function () {
    $normalizedName = collect(Schema::getColumns('tool_shelves'))->firstWhere('name', 'normalized_name');

    expect($normalizedName)->not->toBeNull();

    if (DB::connection()->getDriverName() === 'mysql') {
        expect($normalizedName['collation'])->toBe('utf8mb4_bin');
    }
});
