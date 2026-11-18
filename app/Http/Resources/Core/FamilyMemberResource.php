<?php

namespace App\Http\Resources\Core;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A member in the list of the family, with the numbers of their books and
 * tools (to be counted with `withCount`), and whether it is the reader.
 *
 * @mixin User
 */
class FamilyMemberResource extends MemberResource
{
    /**
     * @return array{id: int, name: string, books_count: int, tools_count: int, own: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'books_count' => (int) $this->books_count,
            'tools_count' => (int) $this->tools_count,
            'own' => $request->user()?->is($this->resource) ?? false,
        ];
    }
}
