<?php

namespace App\Http\Resources\Admin;

use App\Services\Admin\GoogleBooksPeriodSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GoogleBooksPeriodSummary $resource
 */
class GoogleBooksPeriodResource extends JsonResource
{
    /**
     * @return array{key: string, label: string, total: int, averageDurationMs: int|null, statuses: list<array{status: string, label: string, count: int}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'total' => $this->resource->total,
            'averageDurationMs' => $this->resource->averageDurationMs,
            'statuses' => $this->resource->statuses,
        ];
    }
}
