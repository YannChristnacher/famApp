<?php

namespace App\Support;

/**
 * Hides the secrets a log line or an exception message may carry before it
 * is shown on the admin control page: values of keys that look secret
 * (password=…, token: …, "signature":"…", api_key=…), bearer tokens and
 * Google API keys. A defence in depth, not a guarantee.
 */
final class SecretMasker
{
    public const MASK = '***';

    private const BEARER_TOKEN = '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i';

    private const GOOGLE_API_KEY = '/\bAIza[0-9A-Za-z_-]{35}/';

    private const SECRET_KEY_VALUE = '/(\b[\w-]*(?:password|passwd|secret|token|signature|key|authorization|cookie)[\w-]*)("?\s*[:=]\s*"?)([^\s&"\',;]+)/i';

    public static function mask(string $text): string
    {
        $masked = (string) preg_replace(self::BEARER_TOKEN, self::MASK, $text);
        $masked = (string) preg_replace(self::GOOGLE_API_KEY, self::MASK, $masked);

        return (string) preg_replace(self::SECRET_KEY_VALUE, '$1$2'.self::MASK, $masked);
    }
}
