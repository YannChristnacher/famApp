<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;

use function Pest\Laravel\post;

it('creates the first tool shelf in position 1 with a trimmed name', function () {
    $user = signIn();
    post(route('garage.shelves.store'), ['name' => '  Électroportatif  '])
        ->assertRedirect(route('garage.shelves.index'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "L'étagère « Électroportatif » a été créée."]);

    $shelf = ToolShelf::query()->sole();
    expect($shelf->user_id)->toBe($user->id)
        ->and($shelf->name)->toBe('Électroportatif')
        ->and($shelf->position)->toBe(1);
});

it('adds a new tool shelf after the existing ones', function () {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    ToolShelf::factory()->for(User::factory())->create(['name' => 'Ailleurs', 'position' => 7]);

    post(route('garage.shelves.store'), ['name' => 'Jardin'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel', 'Jardin']);
});

it('rejects an invalid name and creates nothing', function (mixed $name) {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);

    post(route('garage.shelves.store'), ['name' => $name])->assertSessionHasErrors('name');

    expect(toolShelfNames($user))->toBe(['Électroportatif']);
})->with([
    'missing' => [null],
    'blank' => ['   '],
    'too long' => [str_repeat('a', 101)],
    'not a string' => [['Électroportatif']],
    'same name' => ['Électroportatif'],
    'other case' => ['éLECTROPORTATIF'],
    'surrounding spaces' => ['  électroportatif '],
]);

it('accepts a name of 100 characters', function () {
    $user = signIn();
    post(route('garage.shelves.store'), ['name' => str_repeat('a', 100)])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe([str_repeat('a', 100)]);
});

it('explains the errors in French', function () {
    $user = signIn();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);

    post(route('garage.shelves.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Le champ nom est obligatoire.']);
    post(route('garage.shelves.store'), ['name' => 'ÉLECTROPORTATIF'])
        ->assertSessionHasErrors(['name' => 'Vous avez déjà une étagère nommée « Électroportatif ».']);
    post(route('garage.shelves.store'), ['name' => str_repeat('a', 101)])
        ->assertSessionHasErrors(['name' => 'Le texte de nom ne peut pas contenir plus de 100 caractères.']);
});

it('accepts a name already used by another user', function () {
    $user = signIn();
    ToolShelf::factory()->for(User::factory())->create(['name' => 'Électroportatif']);

    post(route('garage.shelves.store'), ['name' => 'Électroportatif'])->assertSessionHasNoErrors();

    expect(toolShelfNames($user))->toBe(['Électroportatif']);
});
