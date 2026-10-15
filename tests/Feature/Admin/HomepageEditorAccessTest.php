<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Bulbula\CMS\Models\Page;
use Bulbula\UIEditor\Services\LayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);
});

test('the homepage page exists after migrations so the homepage is editable', function () {
    $homepage = Page::homepage()->first();

    expect($homepage)->not->toBeNull()
        ->and($homepage->url_key)->toBe('home')
        ->and($homepage->status)->toBe('published');
});

test('the homepage backfill migration is idempotent', function () {
    $migration = require base_path(
        'packages/Bulbula/CMS/src/Database/Migrations/2026_10_15_000001_ensure_homepage_page_exists.php'
    );

    // Running the backfill again must not duplicate the homepage record
    // (url_key is unique) nor disturb the existing one.
    $migration->up();
    $migration->up();

    expect(Page::homepage()->count())->toBe(1);
});

test('admin can open the homepage in the block editor', function () {
    $homepage = Page::homepage()->firstOrFail();

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.uieditor.pages.editor', $homepage));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/UIEditor/Editor')
        ->where('pageType', 'homepage')
        ->where('page.is_homepage', true)
        ->has('saveUrl')
        ->has('publishUrl')
    );
});

test('the homepage editor shortcut redirects into the homepage block editor', function () {
    $homepage = Page::homepage()->firstOrFail();

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.uieditor.homepage.editor'))
        ->assertRedirect(route('admin.uieditor.pages.editor', $homepage));
});

test('guests cannot open the homepage block editor', function () {
    $homepage = Page::homepage()->firstOrFail();

    $this->get(route('admin.uieditor.pages.editor', $homepage))
        ->assertRedirect(route('admin.login'));
});

test('admin can save and publish homepage hero slider changes', function () {
    $layout = [
        'version'  => '2.0',
        'sections' => [
            [
                'id'       => 'sec_hero',
                'type'     => 'section',
                'settings' => ['background_color' => '#000000', 'padding_top' => 0, 'padding_bottom' => 0, 'full_width' => true],
                'columns'  => [
                    [
                        'id'       => 'col_hero',
                        'width'    => 12,
                        'settings' => ['padding' => 0, 'align' => 'center'],
                        'blocks'   => [
                            [
                                'id'       => 'blk_hero',
                                'type'     => 'slider',
                                'settings' => [
                                    'height'   => 560,
                                    'autoplay' => true,
                                    'interval' => 5000,
                                    'slides'   => [
                                        [
                                            'id'         => 's1',
                                            'image'      => '',
                                            'grad_from'  => '#1e3a8a',
                                            'grad_to'    => '#2563eb',
                                            'badge'      => 'Sale',
                                            'headline'   => 'Admin Edited Hero Headline',
                                            'subheading' => 'Managed from the admin panel.',
                                            'cta_text'   => 'Shop Now',
                                            'cta_url'    => '/products',
                                            'text_align' => 'center',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.uieditor.homepage.save'), ['layout_data' => $layout])
        ->assertOk()
        ->assertJsonPath('status', 'draft');

    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.uieditor.homepage.publish'))
        ->assertOk()
        ->assertJsonPath('status', 'published');

    $published = app(LayoutService::class)->getPublishedHomepage();

    expect($published)->not->toBeNull()
        ->and($published->layout_data['sections'][0]['columns'][0]['blocks'][0]['settings']['slides'][0]['headline'])
        ->toBe('Admin Edited Hero Headline');
});
