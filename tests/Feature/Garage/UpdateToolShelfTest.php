<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;

use function Pest\Laravel\patch;

it('renames a tool shelf with a trimmed name and keeps its position', function () {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);

    patch(route('garage.shelves.update', $shelf), ['name' => '  Outils à main '])
        ->assertRedirect(route('garage.shelves.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère a été renommée en « Outils à main »."]);

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Outils à main']);
});

it('lets a tool shelf keep its name with another case', function () {
    $user = signIn();
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'électroportatif']);

    patch(route('garage.shelves.update', $shelf), ['name' => 'Électroportatif'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Électroportatif']);
});

it('rejects an invalid name and changes nothing', function (mixed $name) {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);

    patch(route('garage.shelves.update', $shelf), ['name' => $name])->assertSessionHasErrors('name');

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel']);
})->with([
    'missing' => [null],
    'blank' => ['  '],
    'too long' => [str_repeat('a', 101)],
    'name of another tool shelf' => [' ÉLECTROPORTATIF '],
]);

it('accepts the name of a tool shelf of another user', function () {
    $user = signIn();
    ToolShelf::factory()->for(User::factory())->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);

    patch(route('garage.shelves.update', $shelf), ['name' => 'Électroportatif'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Électroportatif']);
});
