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
    'url credentials' => ['connect postgres://admin:s3cret@db:5432/app failed', 'connect postgres://***@db:5432/app failed'],
    'url credentials with https' => ['GET https://paul:p4ss@example.com/x', 'GET https://***@example.com/x'],
    'basic authorization' => ['Authorization: Basic dXNlcjpwYXNz', 'Authorization: Basic ***'],
    'digest authorization' => ['Authorization: Digest username="paul", response="6629fae4"', 'Authorization: Digest ***'],
    'laravel key' => ['APP_KEY base64:AbCdEfGhIjKlMnOpQrStUvWxYz0123456789+/AB= used', 'APP_KEY *** used'],
    'short base64 kept' => ['image base64:abc', 'image base64:abc'],
    'bcrypt hash' => ['hash $2y$10$'.str_repeat('a', 53).' stored', 'hash *** stored'],
    'argon2 hash' => ['hash $argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHQ$aGFzaGhhc2hoYXNo stored', 'hash *** stored'],
    'jwt' => ['jwt eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.sig_nature-1 refused', 'jwt *** refused'],
    'pwd' => ['pwd=x1', 'pwd=***'],
    'pass' => ['pass=x1', 'pass=***'],
    'credential' => ['credentials: x1', 'credentials: ***'],
    'session' => ['laravel_session=x1', 'laravel_session=***'],
    'csrf' => ['csrf=x1', 'csrf=***'],
    'remember' => ['remember_web_59ba=x1', 'remember_web_59ba=***'],
    'json value with spaces' => ['{"password":"a b c","name":"Paul"}', '{"password":"***","name":"Paul"}'],
    'json value with an escaped quote' => ['{"secret":"a \\" b","name":"Paul"}', '{"secret":"***","name":"Paul"}'],
]);
