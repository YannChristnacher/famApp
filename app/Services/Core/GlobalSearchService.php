<?php

namespace App\Services\Core;

use App\Models\User;
use App\Services\Core\Search\SearchGroup;
use App\Services\Core\Search\SearchRegistry;
use App\Services\Core\Search\SearchTerm;

/**
 * The search of the header: asks every registered source of the modules and
 * keeps the groups that found something, in the order of the registry.
 */
class GlobalSearchService
{
    public const RESULTS_PER_GROUP = 5;

    public function __construct(private readonly SearchRegistry $registry) {}

    /**
     * @return list<SearchGroup>
     */
    public function search(string $query, User $searcher): array
    {
        $term = new SearchTerm($query);
        $groups = [];

        foreach ($this->registry->sources() as $source) {
            $results = $source->search($term, $searcher, self::RESULTS_PER_GROUP)->take(self::RESULTS_PER_GROUP)->values();

            if ($results->isNotEmpty()) {
                $groups[] = new SearchGroup($source->key(), $source->label(), $source->icon(), $results);
            }
        }

        return $groups;
    }
}
