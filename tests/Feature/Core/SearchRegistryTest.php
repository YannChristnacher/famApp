<?php

use App\Models\User;
use App\Services\Core\Search\SearchRegistry;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchSource;
use App\Services\Core\Search\SearchTerm;
use Illuminate\Support\Collection;

use function Pest\Laravel\getJson;

/**
 * A module added later: only its source has to be written and registered.
 */
class FakeRecipeSearchSource implements SearchSource
{
    public function key(): string
    {
        return 'recipes';
    }

    public function label(): string
    {
        return 'Recettes';
    }

    public function icon(): string
    {
        return 'i-lucide-chef-hat';
    }

    public function search(SearchTerm $term, User $searcher, int $limit): Collection
    {
        return collect([
            new SearchResult('recipe', 1, "Tarte ({$term->value})", "chez {$searcher->name}", '/recipes/1', null),
            new SearchResult('recipe', 2, 'Quiche', null, '/recipes/2', null),
        ])->take($limit);
    }
}

it('registers the sources of the modules in order', function () {
    expect(collect(app(SearchRegistry::class)->sources())->map(fn (SearchSource $source) => $source->key())->all())
        ->toBe(['books', 'tools', 'members']);
});

it('answers the results of a source registered later', function () {
    app(SearchRegistry::class)->register(FakeRecipeSearchSource::class);
    signIn();

    $groups = getJson(route('search', ['q' => 'tarte']))->assertOk()->json('groups');

    expect($groups)->toBe([[
        'key' => 'recipes',
        'label' => 'Recettes',
        'icon' => 'i-lucide-chef-hat',
        'results' => [
            ['type' => 'recipe', 'id' => 1, 'label' => 'Tarte (tarte)', 'description' => 'chez '.User::query()->sole()->name, 'url' => '/recipes/1', 'image' => null],
            ['type' => 'recipe', 'id' => 2, 'label' => 'Quiche', 'description' => null, 'url' => '/recipes/2', 'image' => null],
        ],
    ]]);
});
