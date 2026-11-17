<?php

use App\Rules\Library\Isbn;
use Illuminate\Support\Facades\Validator;

it('normalizes an ISBN by removing hyphens and spaces and upper-casing the final X', function (string $input, string $normalized) {
    expect(Isbn::normalize($input))->toBe($normalized);
})->with([
    'hyphens' => ['978-2-07-036822-8', '9782070368228'],
    'spaces' => ['2 07 036822 x', '207036822X'],
    'surrounding spaces' => ['  0306406152 ', '0306406152'],
    'already normalized' => ['9780306406157', '9780306406157'],
]);

it('accepts valid ISBN-10 and ISBN-13', function (string $isbn) {
    expect(Validator::make(['isbn' => $isbn], ['isbn' => [new Isbn]])->passes())->toBeTrue();
})->with(['0306406152', '080442957X', '207036822X', '9780306406157', '9782070368228', '9791032305690']);

it('refuses malformed ISBNs and wrong check digits with a French message', function (string $isbn) {
    $validator = Validator::make(['isbn' => $isbn], ['isbn' => [new Isbn]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('isbn'))->toBe('Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide.');
})->with([
    'wrong ISBN-10 check digit' => ['0306406153'],
    'wrong ISBN-13 check digit' => ['9780306406158'],
    'X inside an ISBN-10' => ['03064X6152'],
    'X at the end of an ISBN-13' => ['978030640615X'],
    'too short' => ['030640615'],
    'eleven digits' => ['03064061520'],
    'too long' => ['97803064061570'],
    'letters' => ['abcdefghij'],
    'not normalized' => ['978-0-306-40615-7'],
]);
