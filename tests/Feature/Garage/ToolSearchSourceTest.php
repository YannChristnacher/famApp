<?php

use App\Models\Garage\Brand;
use App\Models\Garage\Tool;
use App\Models\User;
use App\Services\Core\Search\SearchResult;
use App\Services\Core\Search\SearchTerm;
use App\Services\Garage\ToolSearchSource;
use Illuminate\Support\Facades\Storage;

/**
 * @return list<string>
 */
function toolLabels(string $term, int $limit = 5): array
{
    return app(ToolSearchSource::class)
        ->search(new SearchTerm($term), User::factory()->create(), $limit)
        ->map(fn (SearchResult $result) => $result->label)
        ->all();
}

it('describes its group in French', function () {
    $source = app(ToolSearchSource::class);

    expect($source->key())->toBe('tools')
        ->and($source->label())->toBe('Outils')
        ->and($source->icon())->toBe('i-lucide-wrench');
});

it('finds the tools of every member by name, brand or accessory', function () {
    $bosch = Brand::factory()->create(['name' => 'Bosch']);
    $drill = Tool::factory()->create(['name' => 'Perceuse', 'brand_id' => $bosch->id]);
    $drill->accessories()->create(['name' => 'Mallette', 'position' => 1]);
    $saw = Tool::factory()->create(['name' => 'Scie sauteuse']);
    $saw->accessories()->create(['name' => 'Lames bois', 'position' => 1]);
    Tool::factory()->create(['name' => 'Marteau']);

    expect(toolLabels('perc'))->toBe(['Perceuse'])
        ->and(toolLabels('bosch'))->toBe(['Perceuse'])
        ->and(toolLabels('BOSCH'))->toBe(['Perceuse'])
        ->and(toolLabels('mallette'))->toBe(['Perceuse'])
        ->and(toolLabels('lames'))->toBe(['Scie sauteuse'])
        ->and(toolLabels('tournevis'))->toBe([]);
});

it('takes the LIKE wildcards of the term literally', function () {
    Tool::factory()->create(['name' => 'Clé 10_12']);
    Tool::factory()->create(['name' => 'Clé 10x12']);

    expect(toolLabels('10_1'))->toBe(['Clé 10_12']);
});

it('puts the names starting with the term first and limits the group', function () {
    $makita = Brand::factory()->create(['name' => 'Makita']);
    Tool::factory()->create(['name' => 'Ponceuse', 'brand_id' => $makita->id]);
    Tool::factory()->create(['name' => 'Grande mak']);
    Tool::factory()->create(['name' => 'Maillet']);
    Tool::factory()->create(['name' => 'Makita set']);

    expect(toolLabels('mak'))->toBe(['Makita set', 'Grande mak', 'Ponceuse'])
        ->and(toolLabels('ma', limit: 2))->toBe(['Maillet', 'Makita set']);
});

it('builds a minimal result pointing to the tool page', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['name' => 'Paul']);
    $tool = toolWithPhotos($owner, ['first', 'second'], 'Perceuse');
    $tool->brand()->associate(Brand::factory()->create(['name' => 'Bosch']))->save();

    $result = app(ToolSearchSource::class)->search(new SearchTerm('perceuse'), User::factory()->create(), 5)->sole();

    expect($result->toArray())->toBe([
        'type' => 'tool',
        'id' => $tool->id,
        'label' => 'Perceuse',
        'description' => 'Bosch · chez Paul',
        'url' => "/garage/tools/{$tool->id}",
        'image' => Storage::disk('public')->url('tools/first.jpg'),
    ]);
});

it('describes a tool without brand by its owner only', function () {
    $searcher = User::factory()->create();
    Tool::factory()->for($searcher)->create(['name' => 'Marteau']);

    $result = app(ToolSearchSource::class)->search(new SearchTerm('marteau'), $searcher, 5)->sole();

    expect($result->description)->toBe('chez vous')
        ->and($result->image)->toBeNull();
});
