<?php

namespace App\Http\Resources\Library;

use App\Services\Library\GoogleBooksVolume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the book form needs to prefill itself: never the raw data of Google.
 *
 * @property GoogleBooksVolume $resource
 */
class GoogleBooksLookupResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{volume_id: string, title: string, author: string, cover_url: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'volume_id' => $this->resource->id,
            'title' => $this->resource->title,
            'author' => $this->resource->author,
            'cover_url' => $this->resource->coverUrl,
        ];
    }
}
