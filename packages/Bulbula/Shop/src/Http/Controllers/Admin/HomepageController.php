<?php

declare(strict_types=1);

namespace Bulbula\Shop\Http\Controllers\Admin;

use Bulbula\Core\Services\SettingService;
use Bulbula\Shop\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HomepageController extends Controller
{
    public function __construct(private SettingService $settingService) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Content/Homepage/Index', [
            'settings' => $this->homepageSettings(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'announcement_enabled' => ['required', 'boolean'],
            'announcement_text' => ['nullable', 'string', 'max:180'],
            'hero_eyebrow' => ['nullable', 'string', 'max:80'],
            'hero_title' => ['required', 'string', 'max:140'],
            'hero_description' => ['nullable', 'string', 'max:500'],
            'hero_primary_text' => ['required', 'string', 'max:60'],
            'hero_primary_url' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! is_string($value) || ! $this->isSafeLink($value)) {
                    $fail('Use a relative path, an anchor, or an http(s) URL.');
                }
            }],
            'hero_secondary_text' => ['nullable', 'string', 'max:60'],
            'hero_secondary_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && (! is_string($value) || ! $this->isSafeLink($value))) {
                    $fail('Use a relative path, an anchor, or an http(s) URL.');
                }
            }],
            'hero_image' => ['nullable', 'string', 'max:2048'],
            'hero_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'show_trust_strip' => ['required', 'boolean'],
            'categories_enabled' => ['required', 'boolean'],
            'categories_title' => ['required', 'string', 'max:80'],
            'featured_enabled' => ['required', 'boolean'],
            'featured_title' => ['required', 'string', 'max:80'],
            'featured_count' => ['required', 'integer', 'min:2', 'max:8'],
            'new_arrivals_enabled' => ['required', 'boolean'],
            'new_arrivals_title' => ['required', 'string', 'max:80'],
            'new_arrivals_count' => ['required', 'integer', 'min:2', 'max:8'],
            'promo_enabled' => ['required', 'boolean'],
            'promo_eyebrow' => ['nullable', 'string', 'max:80'],
            'promo_title' => ['required', 'string', 'max:120'],
            'promo_description' => ['nullable', 'string', 'max:400'],
            'promo_cta_text' => ['required', 'string', 'max:60'],
            'promo_cta_url' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! is_string($value) || ! $this->isSafeLink($value)) {
                    $fail('Use a relative path, an anchor, or an http(s) URL.');
                }
            }],
            'promo_image' => ['nullable', 'string', 'max:2048'],
            'promo_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        unset($validated['hero_image_upload'], $validated['promo_image_upload']);

        $defaults = (array) config('shop.homepage.content_defaults', []);
        $settings = array_replace($defaults, $validated);
        $current = $this->homepageSettings();

        $settings['hero_image'] = $request->hasFile('hero_image_upload')
            ? Storage::disk('public')->url($request->file('hero_image_upload')->store('homepage', 'public'))
            : $this->safeImageUrl((string) ($validated['hero_image'] ?? ''), (string) ($current['hero_image'] ?? ''));

        $settings['promo_image'] = $request->hasFile('promo_image_upload')
            ? Storage::disk('public')->url($request->file('promo_image_upload')->store('homepage', 'public'))
            : $this->safeImageUrl((string) ($validated['promo_image'] ?? ''), (string) ($current['promo_image'] ?? ''));

        $settings['hero_primary_url'] = $this->safeLink((string) $settings['hero_primary_url']) ? trim((string) $settings['hero_primary_url']) : '/products';
        $settings['hero_secondary_url'] = $this->safeLink((string) ($settings['hero_secondary_url'] ?? '')) ? trim((string) $settings['hero_secondary_url']) : '#categories';
        $settings['promo_cta_url'] = $this->safeLink((string) $settings['promo_cta_url']) ? trim((string) $settings['promo_cta_url']) : '/products';

        $this->settingService->set('homepage_content', $settings, 'json', 'homepage');

        return back()->with('success', 'Homepage updated successfully.');
    }

    private function homepageSettings(): array
    {
        $defaults = (array) config('shop.homepage.content_defaults', []);
        $saved = $this->settingService->get('homepage_content', []);

        return array_replace($defaults, is_array($saved) ? $saved : []);
    }

    private function isSafeLink(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $value) === 1) {
            return true;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    private function safeImageUrl(string $value, string $fallback = ''): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        if ($this->isSafeLink($value) && ! str_starts_with($value, '#')) {
            return $value;
        }

        return $fallback;
    }
}
