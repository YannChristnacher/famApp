<?php

namespace App\Services\Core\Search;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a module searches for the global search of the header: one group of
 * results (books, tools, members…). A module adds its own source and
 * registers it in SearchServiceProvider; nothing else changes.
 */
interface SearchSource
{
    /** Identifier of the group in the JSON answer ("books"). */
    public function key(): string;

    /** French title of the group in the palette ("Livres"). */
    public function label(): string;

    /** Lucide icon of the results without image ("i-lucide-book"). */
    public function icon(): string;

    /**
     * At most $limit results visible to $searcher, the most relevant first:
     * a label starting with the term before any other match (see
     * SearchTerm::ranked()). Minimal data only: no email, no raw data.
     *
     * @return Collection<int, SearchResult>
     */
    public function search(SearchTerm $term, User $searcher, int $limit): Collection;
}
