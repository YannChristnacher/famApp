<?php

namespace App\Http\Resources\Garage;

use App\Models\Garage\Tool;
use App\Models\Garage\ToolShelf;
use App\Services\Garage\ToolPhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tool of the signed-in member, as listed: thumbnail (first photo), brand
 * and shelves (to be eager loaded).
 *
 * @mixin Tool
 */
class ToolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand === null ? null : ['id' => $this->brand->id, 'name' => $this->brand->name],
            'thumbnail_url' => app(ToolPhotoService::class)->url($this->thumbnail?->path),
            'shelves' => $this->shelves
                ->map(fn (ToolShelf $shelf) => ['id' => $shelf->id, 'name' => $shelf->name])
                ->values()
                ->all(),
        ];
    }
}
