<?php

namespace App\Policies\Garage;

use App\Models\Garage\Tool;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner sees and changes a tool here. Anyone else, admins included,
 * gets a 404, so that the garages of other members are not revealed (their
 * read-only view comes with F11, under its own policy).
 */
class ToolPolicy
{
    public function view(User $user, Tool $tool): Response
    {
        return $this->ownerOnly($user, $tool);
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
