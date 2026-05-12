<?php

namespace InSquare\OpendxpSitemapBundle\Util;

final class HostNormalizer
{
    public static function normalize(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '';
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://' . $host;
        }

        return rtrim($host, '/');
    }

    public static function buildAbsoluteUrl(string $host, string $path): string
    {
        $normalizedHost = self::normalize($host);
        if ($normalizedHost === '') {
            return '';
        }

        $normalizedPath = '/' . ltrim($path, '/');

        return $normalizedHost . $normalizedPath;
    }
}
