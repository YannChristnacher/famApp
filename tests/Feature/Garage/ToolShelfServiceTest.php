<?php

use App\Enums\Garage\ToolShelfMoveDirection;
use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Services\Garage\ToolShelfService;
use Illuminate\Validation\ValidationException;

it('turns a duplicate name caught by the database into a validation error', function () {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);

    expect(fn () => app(ToolShelfService::class)->create($user, 'Électroportatif'))
        ->toThrow(ValidationException::class);

    expect(toolShelfNames($user))->toBe(['Électroportatif']);
});

it('turns a duplicate name caught by the database into a validation error on rename', function () {
    $user = User::factory()->create();
    ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $shelf = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);

    expect(fn () => app(ToolShelfService::class)->rename($shelf, 'Électroportatif'))
        ->toThrow(ValidationException::class);

    expect(toolShelfNames($user))->toBe(['Électroportatif', 'Manuel']);
});

it('moves a stale tool shelf model using the current positions', function () {
    $user = User::factory()->create();
    $first = ToolShelf::factory()->for($user)->create(['name' => 'Électroportatif']);
    $second = ToolShelf::factory()->for($user)->create(['name' => 'Manuel']);
    $stale = ToolShelf::query()->findOrFail($second->id);
    app(ToolShelfService::class)->move($second, ToolShelfMoveDirection::Up);

    app(ToolShelfService::class)->move($stale, ToolShelfMoveDirection::Up);

    expect(toolShelfNames($user))->toBe(['Manuel', 'Électroportatif'])
        ->and($first->fresh()?->position)->toBe(2);
});
