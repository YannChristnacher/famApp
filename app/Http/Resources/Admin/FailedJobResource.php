<?php

namespace App\Http\Resources\Admin;

use App\Services\Admin\FailedJobSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property FailedJobSummary $resource
 */
class FailedJobResource extends JsonResource
{
    /**
     * @return array{uuid: string, queue: string, connection: string, job: string, exception: string, failedAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource->uuid,
            'queue' => $this->resource->queue,
            'connection' => $this->resource->connection,
            'job' => $this->resource->job,
            'exception' => $this->resource->exception,
            'failedAt' => $this->resource->failedAt->toIso8601String(),
        ];
    }
}
