<?php

namespace App\Policies\Garage;

use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner changes a tool shelf. Anyone else, admins included, gets a
 * 404, so that the garages of other members are not revealed.
 */
class ToolShelfPolicy
{
    public function update(User $user, ToolShelf $shelf): Response
    {
        return $this->ownerOnly($user, $shelf);
    }

    public function delete(User $user, ToolShelf $shelf): Response
    {
        return $this->ownerOnly($user, $shelf);
    }

    public function move(User $user, ToolShelf $shelf): Response
    {
        return $this->ownerOnly($user, $shelf);
    }

    private function ownerOnly(User $user, ToolShelf $shelf): Response
    {
        return $shelf->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
