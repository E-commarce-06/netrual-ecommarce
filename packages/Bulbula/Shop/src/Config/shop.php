<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shop Package Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Shop package which handles
    | all storefront functionality including homepage, product browsing,
    | checkout flow, and customer account management.
    |
    */

    'name' => 'Shop',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure route prefixes and middleware for shop routes.
    |
    */
    'routes' => [
        'prefix' => '',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Homepage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure homepage settings and featured content.
    |
    */
    'homepage' => [
        'featured_products_count' => 12,
        'show_slider' => true,
        'show_categories' => true,
        'show_featured_products' => true,
        'show_new_products' => true,
        // CMS Block identifier used for the storefront hero banner.
        // You can point this to a theme/demo identifier (e.g. 'fashion-hero-banner').
        'hero_block_identifier' => 'homepage-hero',
        // Optional: multiple CMS block identifiers for legacy/editor hero slides.
        'hero_block_identifiers' => [],

        // Simple, structured content for the native storefront homepage.
        // These values can be edited from Admin → Content → Homepage.
        'content_defaults' => [
            'announcement_enabled' => true,
            'announcement_text' => 'Good finds. Thoughtfully chosen.',
            'hero_eyebrow' => 'THE BNEO EDIT',
            'hero_title' => 'Find your next favourite.',
            'hero_description' => 'Thoughtful finds for home, work, and everyday living.',
            'hero_primary_text' => 'Shop all products',
            'hero_primary_url' => '/products',
            'hero_secondary_text' => 'Browse categories',
            'hero_secondary_url' => '#categories',
            'hero_image' => '/images/bneo-ethiopian-family-shopping.jpg',
            'show_trust_strip' => true,
            'categories_enabled' => true,
            'categories_title' => 'Shop by category',
            'featured_enabled' => true,
            'featured_title' => 'Featured products',
            'featured_count' => 4,
            'new_arrivals_enabled' => true,
            'new_arrivals_title' => 'New arrivals',
            'new_arrivals_count' => 4,
            'promo_enabled' => true,
            'promo_eyebrow' => 'THE BNEO SELECTION',
            'promo_title' => 'Make everyday feel a little better.',
            'promo_description' => 'Useful, beautiful pieces chosen for everyday life.',
            'promo_cta_text' => 'Explore the collection',
            'promo_cta_url' => '/products',
            'promo_image' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Listing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure product listing page settings.
    |
    */
    'listing' => [
        'products_per_page' => 12,
        'available_limits' => [12, 24, 36, 48],
        'default_sort' => 'position',
        'available_sorts' => [
            'position' => 'Position',
            'name_asc' => 'Name: A to Z',
            'name_desc' => 'Name: Z to A',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'created_at' => 'Newest First',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search functionality.
    |
    */
    'search' => [
        'min_query_length' => 3,
        'max_results' => 20,
        'enable_suggestions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Account Configuration
    |--------------------------------------------------------------------------
    |
    | Configure customer account settings.
    |
    */
    'account' => [
        'orders_per_page' => 10,
        'enable_wishlist' => true,
        'enable_compare' => true,
        'enable_reviews' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout Configuration
    |--------------------------------------------------------------------------
    |
    | Configure checkout flow settings.
    |
    */
    'checkout' => [
        'allow_guest_checkout' => true,
        'require_terms_acceptance' => true,
        'enable_newsletter_signup' => true,
        'enable_order_notes' => true,
        'tax_rate' => 0.08, // 8% tax rate
        'free_shipping_threshold' => 100.00,
        'shipping_rates' => [
            'standard' => 5.00,
            'express' => 15.00,
            'overnight' => 25.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Configuration
    |--------------------------------------------------------------------------
    |
    | Configure order settings.
    |
    */
    'order' => [
        'number_prefix' => 'ORD',
        'auto_cancel_pending_after_days' => 7,
        'allow_cancellation_statuses' => ['pending', 'processing'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SEO settings for shop pages.
    |
    */
    'seo' => [
        'enable_meta_tags' => true,
        'enable_schema_markup' => true,
        'enable_sitemap' => true,
        'enable_breadcrumbs' => true,
    ],
];
