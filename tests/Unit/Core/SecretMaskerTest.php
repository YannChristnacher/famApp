<?php

use App\Support\SecretMasker;

it('masks the values of secret keys', function (string $text, string $masked) {
    expect(SecretMasker::mask($text))->toBe($masked);
})->with([
    'url query' => ['GET /reset?token=abc123&email=a', 'GET /reset?token=***&email=a'],
    'password' => ['password=hunter2 next', 'password=*** next'],
    'signature' => ['/invitation/3?expires=1&signature=5f2b9c', '/invitation/3?expires=1&signature=***'],
    'api key' => ['api_key=XYZ', 'api_key=***'],
    'key in a url' => ['volumes?q=isbn:1&key=AbC', 'volumes?q=isbn:1&key=***'],
    'access token' => ['access_token: tok', 'access_token: ***'],
    'json' => ['{"password":"p4ss","name":"Paul"}', '{"password":"***","name":"Paul"}'],
    'bearer' => ['Authorization: Bearer eyJ.abc.def', 'Authorization: ***'],
    'google key' => ['key AIzaSyA1234567890abcdefghijklmnopqrstuv', 'key ***'],
    'case' => ['PASSWORD=Secret', 'PASSWORD=***'],
    'plain text' => ['Nothing to hide here', 'Nothing to hide here'],
]);
