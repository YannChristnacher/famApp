<?php

namespace App\Services\Library;

/**
 * Which cover URLs the server may download (protection against SSRF): https
 * only, on one of the configured Google hosts, without credentials nor port.
 */
class GoogleBooksCoverPolicy
{
    private const IMAGE_SIZES = ['extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail'];

    /**
     * The largest cover listed in the imageLinks of a trusted Google answer.
     * Google lists its images in http: the scheme is raised to https, and
     * only for an allowed host.
     */
    public function largestCover(mixed $imageLinks): ?string
    {
        if (! is_array($imageLinks)) {
            return null;
        }

        foreach (self::IMAGE_SIZES as $size) {
            $url = $imageLinks[$size] ?? null;
            if (is_string($url)) {
                return $this->upgradedUrl($url);
            }
        }

        return null;
    }

    public function allows(string $url): bool
    {
        if (preg_match('/[\s\x00-\x1f\x7f]/', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts(), true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['port']);
    }

    private function upgradedUrl(string $url): ?string
    {
        $upgraded = preg_replace('#^http://#i', 'https://', $url) ?? $url;

        return $this->allows($upgraded) ? $upgraded : null;
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        /** @var list<string> $hosts */
        $hosts = config('services.google_books.cover_hosts', []);

        return array_map(strtolower(...), $hosts);
    }
}
