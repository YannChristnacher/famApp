<?php

namespace App\Services\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The members of the family, as listed on « La famille ».
 */
class FamilyService
{
    /**
     * Every activated account (admins included; not the pending invitations)
     * by name then id, with the numbers of their books and tools counted in
     * the same query.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return User::query()
            ->activated()
            ->withCount(['books', 'tools'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
