<?php

namespace App\Policies\Library;

use App\Models\Library\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner sees and changes a book (the read-only view of the other
 * members comes with F11). Anyone else gets a 404.
 */
class BookPolicy
{
    public function view(User $user, Book $book): Response
    {
        return $this->ownerOnly($user, $book);
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
