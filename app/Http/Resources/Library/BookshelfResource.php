<?php

namespace App\Http\Resources\Library;

use App\Models\Library\Bookshelf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bookshelf
 */
class BookshelfResource extends JsonResource
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
