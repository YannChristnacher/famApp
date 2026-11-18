<?php

namespace App\Http\Resources\Admin;

use App\Services\Admin\ErrorLogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ErrorLogEntry $resource
 */
class ErrorLogEntryResource extends JsonResource
{
    /**
     * @return array{level: string, message: string, loggedAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->resource->level,
            'message' => $this->resource->message,
            'loggedAt' => $this->resource->loggedAt->toIso8601String(),
        ];
    }
}
