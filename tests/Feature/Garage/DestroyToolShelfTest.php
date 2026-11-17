<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;

use function Pest\Laravel\delete;

it('deletes a tool shelf and closes the gap in the positions', function () {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $middle = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    ToolShelf::factory()->for($user)->create(['name' => 'Jardin']);

    delete(route('garage.shelves.destroy', $middle))
        ->assertRedirect(route('garage.shelves.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère « Manuel » a été supprimée."]);

    expect(ToolShelf::query()->find($middle->id))->toBeNull()
        ->and(toolShelfNames($user))->toBe(['Électroportatif', 'Jardin']);
});

it('deletes the first and the last tool shelf', function () {
    $user = signIn();
    $first = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    $last = ToolShelf::factory()->for($user)->create(['name' => 'Jardin']);

    delete(route('garage.shelves.destroy', $first));
    expect(toolShelfNames($user))->toBe(['Manuel', 'Jardin']);

    delete(route('garage.shelves.destroy', $last));
    expect(toolShelfNames($user))->toBe(['Manuel']);
});

it('leaves the tool shelves of other users untouched', function () {
    $user = signIn();
    $other = User::factory()->create();
    ToolShelf::factory()->for($other)->create(['name' => 'A']);
    ToolShelf::factory()->for($other)->create(['name' => 'B']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);

    delete(route('garage.shelves.destroy', $shelf));

    expect(toolShelfNames($user))->toBe([])
        ->and(toolShelfNames($other))->toBe(['A', 'B']);
});
