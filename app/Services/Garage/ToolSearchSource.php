<?php

namespace App\Services\Garage;

use App\Models\Garage\Tool;
use App\Models\User;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchSource;
use App\Services\Core\Search\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tools of every member, searched by name, brand or accessory; the result
 * leads to the page of the tool.
 */
class ToolSearchSource implements SearchSource
{
    public function __construct(private readonly ToolPhotoService $photos) {}

    public function key(): string
    {
        return 'tools';
    }

    public function label(): string
    {
        return 'Outils';
    }

    public function icon(): string
    {
        return 'i-lucide-wrench';
    }

    public function search(SearchTerm $term, User $searcher, int $limit): Collection
    {
        $query = Tool::query()
            ->select(['id', 'user_id', 'name', 'brand_id'])
            ->with(['user:id,name', 'brand:id,name', 'thumbnail']);

        $matches = function (Builder $any) use ($term): void {
            $term->whereContains($any, 'tools.name');
            $any->orWhereHas('brand', fn (Builder $brand) => $term->whereContains($brand, 'brands.name'));
            $any->orWhereHas('accessories', fn (Builder $accessory) => $term->whereContains($accessory, 'tool_accessories.name'));
        };

        return $term->ranked($query, 'tools.name', $matches, $limit)
            ->map(fn (Tool $tool) => new SearchResult(
                type: 'tool',
                id: $tool->id,
                label: $tool->name,
                description: $this->description($tool, $searcher),
                url: route('garage.tools.show', $tool, false),
                image: $this->photos->url($tool->thumbnail?->path),
            ))
            ->toBase();
    }

    private function description(Tool $tool, User $searcher): string
    {
        $owner = $tool->isOwnedBy($searcher) ? 'chez vous' : 'chez '.$tool->user->name;

        return $tool->brand === null ? $owner : "{$tool->brand->name} · {$owner}";
    }
}
