<?php

namespace App\Policies\Library;

use App\Models\Library\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every signed-in member, admins included, reads the books of the family
 * (F11); only the owner changes or deletes one. Anyone else gets a 404, so
 * that a write never reveals more than the read-only pages.
 */
class BookPolicy
{
    public function view(User $user, Book $book): Response
    {
        return Response::allow();
    }

    public function update(User $user, Book $book): Response
    {
        return $this->ownerOnly($user, $book);
    }

    public function delete(User $user, Book $book): Response
    {
        return $this->ownerOnly($user, $book);
    }

    private function ownerOnly(User $user, Book $book): Response
    {
        return $book->isOwnedBy($user) ? Response::allow() : Response::denyAsNotFound();
    }
}
