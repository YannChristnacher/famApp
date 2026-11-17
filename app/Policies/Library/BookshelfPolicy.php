<?php

namespace App\Policies\Library;

use App\Models\Library\Bookshelf;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner changes a bookshelf. Anyone else gets a 404, so that the
 * bookshelves of other members are not revealed.
 */
class BookshelfPolicy
{
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
