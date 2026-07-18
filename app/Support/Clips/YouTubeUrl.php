<?php

namespace App\Support\Clips;

final class YouTubeUrl
{
    public static function videoId(string $url): ?string
    {
        $normalizedUrl = trim($url);
        $parts = parse_url($normalizedUrl);

        if (is_array($parts) && ! isset($parts['host']) && preg_match('/^(www\.)?(m\.)?(youtube\.com|youtu\.be)\//i', $normalizedUrl)) {
            $parts = parse_url("https://{$normalizedUrl}");
        }

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be') {
            return self::validId(strtok($path, '/') ?: null);
        }

        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            return null;
        }

        if (str_starts_with($path, 'shorts/')) {
            return self::validId(explode('/', $path)[1] ?? null);
        }

        if ($path === 'watch') {
            parse_str((string) ($parts['query'] ?? ''), $query);

            return self::validId(is_string($query['v'] ?? null) ? $query['v'] : null);
        }

        return null;
    }

    private static function validId(?string $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $value)) {
            return null;
        }

        return $value;
    }
}
