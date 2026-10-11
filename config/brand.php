<?php

/*
|--------------------------------------------------------------------------
| Brand Configuration
|--------------------------------------------------------------------------
|
| Central place for the product name and brand identity. To rebrand the
| app, change the values here or set the matching BRAND_* variables in .env.
| Nothing else needs to be edited for the visible name, links and contacts.
|
*/

return [

    'name' => env('BRAND_NAME', 'Bulbula'),

    'slug' => env('BRAND_SLUG', 'bulbula'),

    'vendor' => env('BRAND_VENDOR', 'bulbula'),

    'homepage' => env('BRAND_HOMEPAGE', 'https://github.com/E-commarce-06/netrual-ecommarce'),

    'author_url' => env('BRAND_AUTHOR_URL'),

    'support_email' => env('BRAND_SUPPORT_EMAIL'),

    /*
    | Optional newsletter sign-up relay. Leave empty to disable (no external calls).
    */
    'newsletter' => [
        'url' => env('BRAND_NEWSLETTER_URL'),
        'secret' => env('BRAND_NEWSLETTER_SECRET'),
    ],

    /*
    | Optional features that call external services. All are OFF by default.
    */
    'features' => [
        'remote_theme_directory' => (bool) env('BRAND_REMOTE_THEME_DIRECTORY', false),
    ],

];
