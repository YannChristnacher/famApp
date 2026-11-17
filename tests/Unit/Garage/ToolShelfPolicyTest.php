<?php

use App\Models\Garage\ToolShelf;
use App\Models\User;
use App\Policies\Garage\ToolShelfPolicy;

function toolShelfUserWithId(int $id, bool $admin = false): User
{
    $user = $admin ? User::factory()->admin()->make() : User::factory()->make();
    $user->id = $id;

    return $user;
}

it('lets only the owner change a tool shelf', function (string $ability) {
    $shelf = ToolShelf::factory()->make(['user_id' => 1, 'position' => 1]);
    $policy = new ToolShelfPolicy;

    expect($policy->{$ability}(toolShelfUserWithId(1), $shelf)->allowed())->toBeTrue();

    $denied = $policy->{$ability}(toolShelfUserWithId(2), $shelf);
    expect($denied->denied())->toBeTrue()
        ->and($denied->status())->toBe(404);

    expect($policy->{$ability}(toolShelfUserWithId(3, admin: true), $shelf)->denied())->toBeTrue();
})->with(['update', 'delete', 'move']);

it('is the policy of the tool shelf model', function () {
    expect(Gate::getPolicyFor(ToolShelf::class))->toBeInstanceOf(ToolShelfPolicy::class);
});
