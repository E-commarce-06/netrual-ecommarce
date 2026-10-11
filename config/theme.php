<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Theme
    |--------------------------------------------------------------------------
    |
    | The slug of the currently active theme. This is set dynamically by
    | ThemeServiceProvider during boot based on the database record.
    | Null means the default theme will be used.
    |
    */
    'active' => null,

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The slug of the default fallback theme. If the active theme doesn't
    | have a required view, the resolver will fall back to this theme.
    |
    */
    'default' => 'bulbula-default',

    /*
    |--------------------------------------------------------------------------
    | Storefront templates (single source of truth)
    |--------------------------------------------------------------------------
    |
    | All storefront themes live under templates/storefront/{category}/{slug}/.
    | There is no separate themes/ directory. Use ThemePathResolver for paths.
    |
    */
    'catalog_path' => base_path('templates'),
    'catalog_registry' => base_path('templates/registry.json'),
    'storefront_path' => base_path('templates/storefront'),

    /*
    |--------------------------------------------------------------------------
    | Remote Template Catalog (optional)
    |--------------------------------------------------------------------------
    |
    | When set, Bulbula merges templates from this registry URL with the local
    | catalog. Intended for https://example.com marketplace feeds.
    |
    */
    'catalog_remote_url' => env('BULBULA_TEMPLATE_CATALOG_URL'),

    /*
    |--------------------------------------------------------------------------
    | Bulbula Theme Directory (bulbula-home API)
    |--------------------------------------------------------------------------
    |
    | Browse and one-click install themes from the official Bulbula directory.
    | Set BULBULA_THEME_DIRECTORY_URL to the bulbula-home API base (e.g. https://example.com/api)
    | and BULBULA_THEME_API_KEY to a key generated in the bulbula-home admin.
    |
    */
    'directory' => [
        'url' => env('BULBULA_THEME_DIRECTORY_URL')
            ?: env('BULBULA_TEMPLATE_CATALOG_URL')
            ?: '',
        'api_key' => env('BULBULA_THEME_API_KEY'),
        'cache_ttl' => (int) env('BULBULA_THEME_DIRECTORY_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rebuild storefront assets after theme install
    |--------------------------------------------------------------------------
    |
    | Theme Vue pages are bundled by Vite at build time. After installing a new
    | theme, npm run build must run so the storefront can load its pages.
    |
    */
    'rebuild_assets_on_install' => (bool) env('BULBULA_THEME_REBUILD_ASSETS', true),
    'rebuild_assets_timeout' => (int) env('BULBULA_THEME_REBUILD_TIMEOUT', 300),
];
