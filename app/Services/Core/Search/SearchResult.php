<?php

namespace App\Services\Core\Search;

/**
 * One result of the global search: what the palette shows and where it leads.
 */
final readonly class SearchResult
{
    /**
     * @param  string  $url  Relative URL of the page of the result.
     * @param  string|null  $image  URL of a picture (cover, thumbnail).
     */
    public function __construct(
        public string $type,
        public int $id,
        public string $label,
        public ?string $description,
        public string $url,
        public ?string $image,
    ) {}

    /**
     * @return array{type: string, id: int, label: string, description: string|null, url: string, image: string|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'url' => $this->url,
            'image' => $this->image,
        ];
    }
}
