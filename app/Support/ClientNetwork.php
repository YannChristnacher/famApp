<?php

namespace App\Support;

/**
 * Key under which a client is rate limited. An IPv6 subscriber usually gets a
 * whole /64 network and can pick a new address in it at will, so IPv6
 * addresses are grouped by their /64 prefix; an IPv4 address stays as is.
 */
final class ClientNetwork
{
    private const IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public static function rateLimitKey(?string $ip): string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return (string) $ip;
        }

        $packed = (string) inet_pton($ip);

        if (str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
