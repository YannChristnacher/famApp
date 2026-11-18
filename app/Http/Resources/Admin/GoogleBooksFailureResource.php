<?php

namespace App\Http\Resources\Admin;

use App\Models\Library\GoogleBooksRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoogleBooksRequest
 */
class GoogleBooksFailureResource extends JsonResource
{
    /**
     * @return array{id: int, type: string, query: string, status: string, statusLabel: string, httpStatus: int|null, member: string|null, createdAt: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->label(),
            'query' => $this->query,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'httpStatus' => $this->http_status,
            'member' => $this->user?->name,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
