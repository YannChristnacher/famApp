<?php

namespace App\Http\Resources\Admin;

use App\Models\Garage\Tool;
use App\Models\Library\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A book or a tool among the latest additions (admin control page).
 *
 * @property Book|Tool $resource
 */
class RecentAdditionResource extends JsonResource
{
    /**
     * @return array{type: string, id: int, label: string, owner: string, createdAt: string|null, url: string}
     */
    public function toArray(Request $request): array
    {
        $addition = $this->resource;

        return [
            'type' => $addition instanceof Book ? 'book' : 'tool',
            'id' => $addition->id,
            'label' => $addition instanceof Book ? $addition->title : $addition->name,
            'owner' => $addition->user->name,
            'createdAt' => $addition->created_at?->toIso8601String(),
            'url' => $addition instanceof Book
                ? route('library.books.show', $addition, false)
                : route('garage.tools.show', $addition, false),
        ];
    }
}
