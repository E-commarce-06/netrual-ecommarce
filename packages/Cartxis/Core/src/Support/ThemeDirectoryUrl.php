<?php

declare(strict_types=1);

namespace Cartxis\Core\Support;

class ThemeDirectoryUrl
{
    public const DEFAULT = '';

    public static function resolve(?string $configuredUrl = null): string
    {
        $url = trim((string) ($configuredUrl ?? config('theme.directory.url', '')));

        if ($url === '') {
            $url = self::DEFAULT;
        }

        if ($url === '' || ! config('brand.features.remote_theme_directory', false)) {
            return '';
        }

        return rtrim(self::normalize($url), '/');
    }

    /**
     * www.cartxis.com redirects POST requests and breaks key registration (HTTP 405).
     */
    public static function normalize(string $url): string
    {
        return str_replace('://www.cartxis.com', '://cartxis.com', $url);
    }
}
