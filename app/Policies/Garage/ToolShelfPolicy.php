<?php

namespace App\Policies\Garage;

use App\Models\Garage\ToolShelf;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every signed-in member, admins included, sees the tool shelves of the family
 * (F11); only the owner changes, deletes or moves one. Anyone else, admins
 * included, gets a 404.
 */
class ToolShelfPolicy
{
    public function view(User $user, ToolShelf $shelf): Response
    {
        return Response::allow();
    }

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
