<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;

use function Pest\Laravel\patch;

/**
 * Signs a member in with the tool shelves Électroportatif, Manuel and Jardin, in that order.
 *
 * @return array{User, list<ToolShelf>}
 */
function signInWithThreeToolShelves(): array
{
    $user = signIn();
    $shelves = array_map(
        fn (string $name) => ToolShelf::factory()->for($user)->create(['name' => $name]),
        ['Électroportatif', 'Manuel', 'Jardin'],
    );

    return [$user, $shelves];
}

it('moves a tool shelf up or down by one place', function (int $index, string $direction, array $expected) {
    [$user, $shelves] = signInWithThreeToolShelves();
    patch(route('garage.shelves.move', $shelves[$index]), ['direction' => $direction])
        ->assertRedirect(route('garage.shelves.index'))
        ->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe($expected);
})->with([
    'second up' => [1, 'up', ['Manuel', 'Électroportatif', 'Jardin']],
    'second down' => [1, 'down', ['Électroportatif', 'Jardin', 'Manuel']],
    'last up' => [2, 'up', ['Électroportatif', 'Jardin', 'Manuel']],
    'first down' => [0, 'down', ['Manuel', 'Électroportatif', 'Jardin']],
]);

it('leaves the order unchanged at the bounds', function (int $index, string $direction) {
    [$user, $shelves] = signInWithThreeToolShelves();
    patch(route('garage.shelves.move', $shelves[$index]), ['direction' => $direction])
        ->assertRedirect(route('garage.shelves.index'))
        ->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel', 'Jardin']);
})->with([
    'first up' => [0, 'up'],
    'last down' => [2, 'down'],
]);

it('rejects a missing or unknown direction', function (mixed $direction) {
    [$user, $shelves] = signInWithThreeToolShelves();
    patch(route('garage.shelves.move', $shelves[1]), ['direction' => $direction])
        ->assertSessionHasErrors('direction');

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel', 'Jardin']);
})->with([
    'missing' => [null],
    'unknown' => ['left'],
    'array' => [['up']],
]);

it('compacts the positions when moving', function () {
    [$user, $shelves] = signInWithThreeToolShelves();
    $shelves[0]->update(['position' => 4]);
    $shelves[1]->update(['position' => 9]);
    $shelves[2]->update(['position' => 12]);

    patch(route('garage.shelves.move', $shelves[2]), ['direction' => 'up']);

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Jardin', 'Manuel']);
});

it('only reorders the tool shelves of the owner', function () {
    [$user, $shelves] = signInWithThreeToolShelves();
    $other = User::factory()->create();
    ToolShelf::factory()->for($other)->create(['name' => 'A']);
    ToolShelf::factory()->for($other)->create(['name' => 'B']);

    patch(route('garage.shelves.move', $shelves[0]), ['direction' => 'down']);

    expect(toolShelfNames($other))->toBe(['A', 'B']);
});
