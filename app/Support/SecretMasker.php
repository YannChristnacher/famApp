<?php

namespace App\Support;

/**
 * Hides the secrets a log line or an exception message may carry before it
 * is shown on the admin control page: credentials in URLs, Authorization
 * values, bearer tokens, JWTs, Google API keys, Laravel keys (base64:…),
 * password hashes, and the values of keys that look secret (password=…,
 * token: …, "signature":"…", api_key=…). A defence in depth, not a guarantee.
 */
final class SecretMasker
{
    public const MASK = '***';

    /** Names of keys whose value is hidden (matched inside a longer name: api_key, csrf_token…). */
    private const SECRET_NAMES = 'password|passwd|pwd|pass|secret|token|signature|key|authorization|credential|cookie|session|csrf|remember_';

    /**
     * Patterns replaced in this order, each by its replacement.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        // scheme://user:password@host
        '/\b([a-z][a-z0-9+.-]*:\/\/)[^\s\/@:]+:[^\s\/@]*@/i' => '$1'.self::MASK.'@',
        // Authorization: Basic …, Authorization: Digest … (rest of the line)
        '/\b(Basic|Digest)\s+[^\r\n]+/' => '$1 '.self::MASK,
        '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i' => self::MASK,
        '/\beyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/' => self::MASK,
        '/\bAIza[0-9A-Za-z_-]{35}/' => self::MASK,
        '/\bbase64:[A-Za-z0-9+\/]{20,}={0,2}/' => self::MASK,
        // bcrypt and argon2 hashes
        '/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/' => self::MASK,
        '/\$argon2(?:id|i|d)\$v=\d+\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+\/]+\$[A-Za-z0-9+\/]+/' => self::MASK,
    ];

    public static function mask(string $text): string
    {
        $masked = $text;

        foreach (self::PATTERNS as $pattern => $replacement) {
            $masked = (string) preg_replace($pattern, $replacement, $masked);
        }

        // JSON value between quotes, spaces included: "password":"a b"
        $masked = (string) preg_replace(
            '/("[\w-]*(?:'.self::SECRET_NAMES.')[\w-]*"\s*:\s*")(?:[^"\\\\]|\\\\.)*(")/i',
            '$1'.self::MASK.'$2',
            $masked,
        );

        // name=value, name: value; an already masked value or a kept scheme (Basic ***) is left as is.
        return (string) preg_replace(
            '/(\b[\w-]*(?:'.self::SECRET_NAMES.')[\w-]*)("?\s*[:=]\s*"?)(?!(?:Basic|Digest) \*\*\*)([^\s&"\',;]+)/i',
            '$1$2'.self::MASK,
            $masked,
        );
    }
}
