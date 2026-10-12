<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active Theme
    |--------------------------------------------------------------------------
    |
    | The slug of the currently active theme. CoreServiceProvider sets this at
    | boot from the 'default' theme below. It is not chosen in the admin.
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
    'default' => (require __DIR__.'/brand.php')['default_theme'],

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
