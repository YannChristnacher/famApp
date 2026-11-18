<?php

use App\Models\Garage\Tool;
use App\Models\Library\Book;
use App\Models\User;
use App\Services\Core\MemberSearchSource;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchTerm;

/**
 * @return list<string>
 */
function memberLabels(string $term, ?User $searcher = null, int $limit = 5): array
{
    return app(MemberSearchSource::class)
        ->search(new SearchTerm($term), $searcher ?? User::factory()->create(['name' => 'Zoé']), $limit)
        ->map(fn (SearchResult $result) => $result->label)
        ->all();
}

it('describes its group in French', function () {
    $source = app(MemberSearchSource::class);

    expect($source->key())->toBe('members')
        ->and($source->label())->toBe('Membres')
        ->and($source->icon())->toBe('i-lucide-user');
});

it('finds the activated members by name only', function () {
    User::factory()->create(['name' => 'Marie Dupont', 'email' => 'zz-marie@example.com']);
    User::factory()->admin()->create(['name' => 'Marc Admin']);
    User::factory()->invited()->create(['name' => 'Marine Invitée']);

    expect(memberLabels('mar'))->toBe(['Marc Admin', 'Marie Dupont'])
        ->and(memberLabels('DUPONT'))->toBe(['Marie Dupont'])
        ->and(memberLabels('zz-marie'))->toBe([])
        ->and(memberLabels('invitée'))->toBe([]);
});

it('puts the names starting with the term first and limits the group', function () {
    User::factory()->create(['name' => 'Anne-Marie']);
    User::factory()->create(['name' => 'Marie']);
    User::factory()->create(['name' => 'Marius']);

    expect(memberLabels('mar'))->toBe(['Marie', 'Marius', 'Anne-Marie'])
        ->and(memberLabels('mar', limit: 1))->toBe(['Marie']);
});

it('takes the LIKE wildcards of the term literally', function () {
    User::factory()->create(['name' => 'Jean_Luc']);
    User::factory()->create(['name' => 'Jean-Luc']);

    expect(memberLabels('n_l'))->toBe(['Jean_Luc']);
});

it('builds a minimal result pointing to the member page with their counts', function () {
    $member = User::factory()->create(['name' => 'Paul']);
    Book::factory()->for($member)->count(2)->create();
    Tool::factory()->for($member)->create();
    $searcher = User::factory()->create(['name' => 'Zoé']);

    $result = app(MemberSearchSource::class)->search(new SearchTerm('paul'), $searcher, 5)->sole();

    expect($result->toArray())->toBe([
        'type' => 'member',
        'id' => $member->id,
        'label' => 'Paul',
        'description' => '2 livres · 1 outil',
        'url' => "/family/{$member->id}",
        'image' => null,
    ]);
});

it('marks the searcher in their own result', function () {
    $searcher = User::factory()->create(['name' => 'Zoé']);

    $result = app(MemberSearchSource::class)->search(new SearchTerm('zoé'), $searcher, 5)->sole();

    expect($result->description)->toBe('Vous · 0 livre · 0 outil');
});
