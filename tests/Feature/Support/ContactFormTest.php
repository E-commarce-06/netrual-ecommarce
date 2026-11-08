<?php

use Bulbula\Core\Services\SettingService;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Mail;

test('contact us uses the same admin-configured name as the rest of the storefront', function () {
    app(SettingService::class)->set('site_name', 'BNEO', 'string', 'general');
    app(SettingService::class)->set('store_name', 'Arkeonet Commerce', 'string', 'store');
    app(SettingService::class)->set('support_email', 'support@example.test');
    app(SettingService::class)->set('store_phone', '+251 911 234 567');
    app(SettingService::class)->set('store_whatsapp', '+251 911 234 567');

    $this->get('/contact-us')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/bulbula-default/pages/Contact/Index')
            ->where('siteConfig.name', 'Arkeonet Commerce')
            ->where('storeName', 'Arkeonet Commerce')
            ->where('supportEmail', 'support@example.test')
            ->where('phone', '+251 911 234 567')
            ->where('whatsapp', '+251 911 234 567')
        );
});

test('a visitor can send the contact form to the configured support inbox', function () {
    app(SettingService::class)->set('support_email', 'support@example.test');

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(function (string $body, callable $callback): bool {
            $message = \Mockery::mock();
            $message->shouldReceive('to')->once()->with('support@example.test')->andReturnSelf();
            $message->shouldReceive('replyTo')->once()->with('customer@example.test', 'Test Customer')->andReturnSelf();
            $message->shouldReceive('subject')->once()->with('[Contact form] Order question')->andReturnSelf();
            $callback($message);

            return str_contains($body, 'Name: Test Customer')
                && str_contains($body, 'Email: customer@example.test')
                && str_contains($body, 'Can you help with my order?');
        });

    $this->from('/contact-us')->post('/contact-us', [
        'name' => 'Test Customer',
        'email' => 'customer@example.test',
        'subject' => 'Order question',
        'message' => 'Can you help with my order?',
    ])
        ->assertRedirect('/contact-us')
        ->assertSessionHas('success');
});

test('contact form validates fields and redirects back with errors', function () {
    $this->from('/contact-us')->post('/contact-us', [
        'name' => 'A',
        'email' => 'not-an-email',
        'subject' => 'Hi',
        'message' => 'Short',
    ])
        ->assertRedirect('/contact-us')
        ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
});
