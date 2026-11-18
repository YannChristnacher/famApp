<?php

namespace App\Policies\Garage;

use App\Models\Garage\Tool;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every signed-in member, admins included, reads the tools of the family
 * (F11); only the owner changes or deletes one. Anyone else gets a 404, so
 * that a write never reveals more than the read-only pages.
 */
class ToolPolicy
{
    public function view(User $user, Tool $tool): Response
    {
        return Response::allow();
    }

    public function update(User $user, Tool $tool): Response
    {
        return $this->ownerOnly($user, $tool);
    }

    public function delete(User $user, Tool $tool): Response
    {
        return $this->ownerOnly($user, $tool);
    }

    private function ownerOnly(User $user, Tool $tool): Response
    {
        return $tool->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
