<?php

use App\Services\Core\Search\SearchTerm;

it('trims the term', function () {
    expect((new SearchTerm('  hobbit '))->value)->toBe('hobbit');
});

it('escapes the LIKE wildcards and the escape character in its patterns', function () {
    $term = new SearchTerm('100%_a!b\\c');

    expect($term->startsWithPattern())->toBe('100!%!_a!!b\\c%')
        ->and($term->containsPattern())->toBe('%100!%!_a!!b\\c%');
});
