<?php

namespace App\Services\Core\Search;

use Illuminate\Support\Collection;

/**
 * The results of one source, with the French title and icon of its group.
 */
final readonly class SearchGroup
{
    /**
     * @param  Collection<int, SearchResult>  $results
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public Collection $results,
    ) {}
}
