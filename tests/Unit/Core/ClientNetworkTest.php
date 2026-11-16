<?php

use App\Support\ClientNetwork;

it('keeps an IPv4 address as is', function () {
    expect(ClientNetwork::rateLimitKey('203.0.113.42'))->toBe('203.0.113.42');
});

it('gives the same key to the IPv6 addresses of a same /64 network', function () {
    expect(ClientNetwork::rateLimitKey('2001:db8:1:2::1'))
        ->toBe(ClientNetwork::rateLimitKey('2001:0db8:0001:0002:ffff:eeee:dddd:cccc'))
        ->toBe('2001:db8:1:2::/64');
});

it('gives different keys to different /64 networks', function () {
    expect(ClientNetwork::rateLimitKey('2001:db8:1:2::1'))
        ->not->toBe(ClientNetwork::rateLimitKey('2001:db8:1:3::1'));
});

it('treats an IPv4-mapped IPv6 address as the IPv4 address', function () {
    expect(ClientNetwork::rateLimitKey('::ffff:203.0.113.42'))->toBe('203.0.113.42');
});

it('keeps an unknown or invalid address as a key of its own', function () {
    expect(ClientNetwork::rateLimitKey(null))->toBe('')
        ->and(ClientNetwork::rateLimitKey('pas-une-ip'))->toBe('pas-une-ip');
});
