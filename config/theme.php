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

];
