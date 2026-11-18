<?php

namespace App\Http\Resources\Core;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as shown to the rest of the family: never their email nor any
 * other account data.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
