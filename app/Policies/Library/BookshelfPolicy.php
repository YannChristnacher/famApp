<?php

namespace App\Policies\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every signed-in member, admins included, sees the bookshelves of the family
 * (F11); only the owner changes, deletes or moves one. Anyone else gets a 404.
 */
class BookshelfPolicy
{
    public function view(User $user, Bookshelf $bookshelf): Response
    {
        return Response::allow();
    }

    public function update(User $user, Bookshelf $bookshelf): Response
    {
        return $this->ownerOnly($user, $bookshelf);
    }

    public function delete(User $user, Bookshelf $bookshelf): Response
    {
        return $this->ownerOnly($user, $bookshelf);
    }

    public function move(User $user, Bookshelf $bookshelf): Response
    {
        return $this->ownerOnly($user, $bookshelf);
    }

    private function ownerOnly(User $user, Bookshelf $bookshelf): Response
    {
        return $bookshelf->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
