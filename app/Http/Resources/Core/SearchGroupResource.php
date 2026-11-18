<?php

namespace App\Http\Resources\Core;

use App\Services\Core\Search\SearchGroup;
use App\Services\Core\Search\SearchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A group of the global search: French title, icon and minimal results.
 *
 * @property SearchGroup $resource
 */
class SearchGroupResource extends JsonResource
{
    /**
     * @return array{key: string, label: string, icon: string, results: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'icon' => $this->resource->icon,
            'results' => $this->resource->results->map(fn (SearchResult $result) => $result->toArray())->values()->all(),
        ];
    }
}
