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
     * Every account (admins included) by name then id, with the numbers of
     * their books and tools counted in the same query.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return User::query()
            ->withCount(['books', 'tools'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
