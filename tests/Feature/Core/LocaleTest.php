<?php

use Illuminate\Support\Facades\Validator;

it('uses French as the application locale', function () {
    expect(config('app.locale'))->toBe('fr')
        ->and(config('app.fallback_locale'))->toBe('fr');
});

it('returns validation messages in French', function () {
    $validator = Validator::make(['email' => ''], ['email' => 'required']);

    expect($validator->errors()->first('email'))->toContain('obligatoire');
});
