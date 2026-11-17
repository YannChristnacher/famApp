<?php

use App\Enums\Library\BookshelfMoveDirection;
use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Services\Library\BookshelfService;
use Illuminate\Validation\ValidationException;

it('turns a duplicate name caught by the database into a validation error', function () {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);

    expect(fn () => app(BookshelfService::class)->create($user, 'Fantasy'))
        ->toThrow(ValidationException::class);

    expect(bookshelfNames($user))->toBe(['Fantasy']);
});

it('turns a duplicate name caught by the database into a validation error on rename', function () {
    $user = User::factory()->create();
    Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $bookshelf = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);

    expect(fn () => app(BookshelfService::class)->rename($bookshelf, 'Fantasy'))
        ->toThrow(ValidationException::class);

    expect(bookshelfNames($user))->toBe(['Fantasy', 'Polars']);
});

it('moves a stale bookshelf model using the current positions', function () {
    $user = User::factory()->create();
    $first = Bookshelf::factory()->for($user)->create(['name' => 'Fantasy']);
    $second = Bookshelf::factory()->for($user)->create(['name' => 'Polars']);
    $stale = Bookshelf::query()->findOrFail($second->id);
    app(BookshelfService::class)->move($second, BookshelfMoveDirection::Up);

    app(BookshelfService::class)->move($stale, BookshelfMoveDirection::Up);

    expect(bookshelfNames($user))->toBe(['Polars', 'Fantasy'])
        ->and($first->fresh()?->position)->toBe(2);
});
