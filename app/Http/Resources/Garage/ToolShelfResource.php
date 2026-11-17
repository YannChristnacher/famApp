<?php

namespace App\Http\Resources\Garage;

use App\Models\Garage\ToolShelf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ToolShelf
 */
class ToolShelfResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, position: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
        ];
    }
}
