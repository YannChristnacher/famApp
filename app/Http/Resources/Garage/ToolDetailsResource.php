<?php

namespace App\Http\Resources\Garage;

use App\Models\Garage\Tool;
use App\Models\Garage\ToolAccessory;
use App\Models\Garage\ToolPhoto;
use App\Services\Garage\ToolPhotoService;
use Illuminate\Http\Request;

/**
 * A tool with its photos and accessories, in order, and its comment (page
 * and form of the tool); the lists keep ToolResource.
 *
 * @mixin Tool
 */
class ToolDetailsResource extends ToolResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photos = app(ToolPhotoService::class);

        return [
            ...parent::toArray($request),
            'photos' => $this->photos
                ->map(fn (ToolPhoto $photo) => ['id' => $photo->id, 'url' => $photos->url($photo->path)])
                ->values()
                ->all(),
            'accessories' => $this->accessories
                ->map(fn (ToolAccessory $accessory) => ['id' => $accessory->id, 'name' => $accessory->name])
                ->values()
                ->all(),
            'comment' => $this->comment,
        ];
    }
}
