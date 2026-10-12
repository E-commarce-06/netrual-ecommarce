<?php

/*
|--------------------------------------------------------------------------
| Brand Configuration (single source of truth)
|--------------------------------------------------------------------------
|
| Every visible brand value (store and product name, company, tagline,
| logos, footer and "powered by" text, email sender, default theme and
| MCP server key) is read from this file. To rebrand the whole app, set the
| matching BRAND_* variables in .env. Do not hardcode a brand name in views,
| components, seeders, or services: read it from here instead.
|
| Code identifiers such as PHP namespaces (Bulbula\...), package folder names
| (packages/Bulbula), and artisan command names (bulbula:install) are not
| brand text. They are internal and are not changed by these settings.
|
*/

return [

    /*
    | Product name. Shown in the admin, storefront, emails, and setup wizard.
    */
    'name' => env('BRAND_NAME', 'Bulbula'),

    /*
    | Short lowercase identifier. Used for the MCP server key and similar keys.
    */
    'slug' => env('BRAND_SLUG', 'bulbula'),

    'vendor' => env('BRAND_VENDOR', 'bulbula'),

    /*
    | Company that publishes the store. Used in copyright lines and the admin
    | footer.
    */
    'company' => env('BRAND_COMPANY', 'Bulbula Commerce'),

    /*
    | Short line under the store name (for example, in the storefront footer).
    */
    'tagline' => env('BRAND_TAGLINE', 'E-commerce Platform'),

    /*
    | Text shown after "Powered by" in footers. Leave empty to hide the line.
    */
    'powered_by' => env('BRAND_POWERED_BY', env('BRAND_NAME', 'Bulbula')),

    /*
    | Copyright holder for the footer line. Defaults to the company name.
    */
    'copyright_holder' => env('BRAND_COPYRIGHT_HOLDER', env('BRAND_COMPANY', 'Bulbula Commerce')),

    /*
    | Name used for theme and package authorship metadata.
    */
    'author' => env('BRAND_AUTHOR', env('BRAND_NAME', 'Bulbula') . ' Team'),

    /*
    | Public paths, relative to /public, for logos and icons.
    */
    'logo' => env('BRAND_LOGO', '/logos/bulbula-transparent.png'),

    'logo_icon' => env('BRAND_LOGO_ICON', '/logos/logo_icon.png'),

    'favicon' => env('BRAND_FAVICON', '/logos/favicon.png'),

    /*
    | Theme slug used when the database has no active theme. Must match a folder
    | under templates/storefront/general/.
    */
    'default_theme' => env('BRAND_DEFAULT_THEME', 'bulbula-default'),

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
