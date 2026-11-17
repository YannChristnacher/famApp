<?php

use App\Support\NormalizedName;

it('ignores case, surrounding and repeated spaces but keeps accents', function (string $name, string $normalized) {
    expect(NormalizedName::of($name))->toBe($normalized);
})->with([
    'case' => ['Électroportatif', 'électroportatif'],
    'accented capitals' => ['ÉTÉ', 'été'],
    'ligature' => ['CŒUR', 'cœur'],
    'surrounding spaces' => ['  Manuel  ', 'manuel'],
    'inner spaces and tabs' => ["Outils \t  à   main", 'outils à main'],
    'accents kept' => ['Café', 'café'],
    'unaccented' => ['Cafe', 'cafe'],
]);
