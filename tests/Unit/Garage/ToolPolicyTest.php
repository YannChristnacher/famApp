<?php

use App\Models\Garage\Tool;
use App\Models\User;
use App\Policies\Garage\ToolPolicy;

function toolUserWithId(int $id, bool $admin = false): User
{
    $user = $admin ? User::factory()->admin()->make() : User::factory()->make();
    $user->id = $id;

    return $user;
}

it('lets only the owner see, change or delete a tool', function (string $ability) {
    $tool = Tool::factory()->make(['user_id' => 1]);
    $policy = new ToolPolicy;

    expect($policy->{$ability}(toolUserWithId(1), $tool)->allowed())->toBeTrue();

    $denied = $policy->{$ability}(toolUserWithId(2), $tool);
    expect($denied->denied())->toBeTrue()
        ->and($denied->status())->toBe(404);

    $deniedToAdmin = $policy->{$ability}(toolUserWithId(3, admin: true), $tool);
    expect($deniedToAdmin->denied())->toBeTrue()
        ->and($deniedToAdmin->status())->toBe(404);
})->with(['view', 'update', 'delete']);

it('is the policy of the tool model', function () {
    expect(Gate::getPolicyFor(Tool::class))->toBeInstanceOf(ToolPolicy::class);
});
