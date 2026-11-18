<?php

namespace App\Services\Core;

use App\Models\User;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchSource;
use App\Services\Core\Search\SearchTerm;
use Illuminate\Support\Collection;

/**
 * Members of the family (activated accounts only), searched by name; the
 * result leads to the page of the member.
 */
class MemberSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'members';
    }

    public function label(): string
    {
        return 'Membres';
    }

    public function icon(): string
    {
        return 'i-lucide-user';
    }

    public function search(SearchTerm $term, User $searcher, int $limit): Collection
    {
        $query = User::query()->activated()->select(['id', 'name'])->withCount(['books', 'tools']);

        return $term->ranked($query, 'name', fn ($any) => $term->whereContains($any, 'name'), $limit)
            ->map(fn (User $member) => new SearchResult(
                type: 'member',
                id: $member->id,
                label: $member->name,
                description: $this->description($member, $searcher),
                url: route('family.members.show', $member, false),
                image: null,
            ))
            ->toBase();
    }

    private function description(User $member, User $searcher): string
    {
        $counts = $this->count((int) $member->getAttribute('books_count'), 'livre')
            .' · '.$this->count((int) $member->getAttribute('tools_count'), 'outil');

        return $member->is($searcher) ? "Vous · {$counts}" : $counts;
    }

    /**
     * French plural: 0 and 1 stay singular.
     */
    private function count(int $count, string $noun): string
    {
        return $count > 1 ? "{$count} {$noun}s" : "{$count} {$noun}";
    }
}
