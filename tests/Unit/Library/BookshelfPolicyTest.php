<?php

use App\Models\Library\Bookshelf;
use App\Models\User;
use App\Policies\Library\BookshelfPolicy;

function userWithId(int $id, bool $admin = false): User
{
    $user = $admin ? User::factory()->admin()->make() : User::factory()->make();
    $user->id = $id;

    return $user;
}

it('lets only the owner change a bookshelf', function (string $ability) {
    $bookshelf = Bookshelf::factory()->make(['user_id' => 1, 'position' => 1]);
    $policy = new BookshelfPolicy;

    expect($policy->{$ability}(userWithId(1), $bookshelf)->allowed())->toBeTrue();

    $denied = $policy->{$ability}(userWithId(2), $bookshelf);
    expect($denied->denied())->toBeTrue()
        ->and($denied->status())->toBe(404);

    expect($policy->{$ability}(userWithId(3, admin: true), $bookshelf)->denied())->toBeTrue();
})->with(['update', 'delete', 'move']);

it('is the policy of the bookshelf model', function () {
    expect(Gate::getPolicyFor(Bookshelf::class))->toBeInstanceOf(BookshelfPolicy::class);
});
