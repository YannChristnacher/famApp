<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class RecentLoginResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, lastLoginAt: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
