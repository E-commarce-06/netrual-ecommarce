<?php

declare(strict_types=1);

namespace Bulbula\CMS\Database\Seeders;

use Bulbula\CMS\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates placeholder CMS pages for footer links that otherwise return 404.
 *
 * Every page is clearly marked as a placeholder. Replace the content in the
 * admin before launch. Existing pages (matched by url_key) are never changed.
 */
class PlaceholderLegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['title' => 'Careers', 'url_key' => 'careers'],
            ['title' => 'Help', 'url_key' => 'help'],
            ['title' => 'Privacy Policy', 'url_key' => 'privacy-policy'],
            ['title' => 'Shipping & Returns', 'url_key' => 'shipping-and-returns'],
            ['title' => 'Terms & Conditions', 'url_key' => 'terms-and-conditions'],
        ];

        foreach ($pages as $page) {
            Page::firstOrCreate(
                ['url_key' => $page['url_key']],
                [
                    'title' => $page['title'],
                    'content' => '<h1>' . e($page['title']) . '</h1>'
                        . '<p><strong>Placeholder page.</strong> Replace this text in the admin before launch.</p>',
                    'meta_title' => $page['title'],
                    'meta_description' => $page['title'] . ' (placeholder)',
                    'status' => 'published',
                ],
            );
        }
    }
}
