<?php

namespace Bulbula\Settings\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Bulbula\Core\Services\SettingService;

class StoreConfigurationController
{
    protected $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Settings/StoreConfiguration/Index', [
            'settings' => [
                // Store Details
                'store_name' => (string) ($this->settingService->get('store_name')
                    ?: $this->settingService->get('site_name', '')),
                'store_description' => (string) $this->settingService->get('store_description', ''),
                'business_registration' => (string) $this->settingService->get('business_registration', ''),
                'vat_number' => (string) $this->settingService->get('vat_number', ''),
                'store_license' => (string) $this->settingService->get('store_license', ''),
                
                // Contact Information
                'store_email' => (string) $this->settingService->get('store_email', ''),
                'support_email' => (string) $this->settingService->get('support_email', ''),
                'store_phone' => (string) $this->settingService->get('store_phone', ''),
                'store_phone_alt' => (string) $this->settingService->get('store_phone_alt', ''),
                'store_whatsapp' => (string) $this->settingService->get('store_whatsapp', ''),
                
                // Address Information
                'store_address_1' => (string) $this->settingService->get('store_address_1', ''),
                'store_address_2' => (string) $this->settingService->get('store_address_2', ''),
                'store_city' => (string) $this->settingService->get('store_city', ''),
                'store_state' => (string) $this->settingService->get('store_state', ''),
                'store_postal_code' => (string) $this->settingService->get('store_postal_code', ''),
                'store_country' => (string) $this->settingService->get('store_country', ''),
                
                // Store Timezone
                'store_timezone' => (string) $this->settingService->get('store_timezone', 'UTC'),
                
                // Social Media
                'social_facebook' => (string) $this->settingService->get('social_facebook', ''),
                'social_instagram' => (string) $this->settingService->get('social_instagram', ''),
                'social_twitter' => (string) $this->settingService->get('social_twitter', ''),
                'social_linkedin' => (string) $this->settingService->get('social_linkedin', ''),
                'social_youtube' => (string) $this->settingService->get('social_youtube', ''),
                'social_tiktok' => (string) $this->settingService->get('social_tiktok', ''),
                'social_pinterest' => (string) $this->settingService->get('social_pinterest', ''),
                
                // Policies
                'policy_privacy' => (string) $this->settingService->get('policy_privacy', ''),
                'policy_terms' => (string) $this->settingService->get('policy_terms', ''),
                'policy_return' => (string) $this->settingService->get('policy_return', ''),
                'policy_shipping' => (string) $this->settingService->get('policy_shipping', ''),
                
                // Checkout Options
                'checkout_allow_guest' => (bool) $this->settingService->get('checkout_allow_guest', true),
                'checkout_require_account' => (bool) $this->settingService->get('checkout_require_account', false),
            ],
        ]);
    }

    public function save(Request $request)
    {
        try {
            $section = $request->input('_section');

            if ($section === 'checkout') {
                $validated = $request->validate([
                    'checkout_allow_guest' => 'nullable|boolean',
                    'checkout_require_account' => 'nullable|boolean',
                ]);

                foreach ($validated as $key => $value) {
                    $this->settingService->set($key, $value, 'boolean', 'store');
                }

                return redirect()->route('admin.settings.store.index')
                    ->with('success', 'Store configuration saved successfully.');
            }

            $rules = [
                // Store Details
                'store_name' => 'required|string|max:255',
                'store_description' => 'nullable|string',
                'business_registration' => 'nullable|string|max:255',
                'vat_number' => 'nullable|string|max:255',
                'store_license' => 'nullable|string|max:255',
                'store_timezone' => 'required|string|max:50',

                // Contact Information
                'store_email' => 'required|email|max:255',
                'support_email' => 'nullable|email|max:255',
                'store_phone' => 'required|string|max:50',
                'store_phone_alt' => 'nullable|string|max:50',
                'store_whatsapp' => 'nullable|string|max:50',
                'store_address_1' => 'required|string|max:255',
                'store_address_2' => 'nullable|string|max:255',
                'store_city' => 'required|string|max:100',
                'store_state' => 'required|string|max:100',
                'store_postal_code' => 'required|string|max:20',
                'store_country' => 'required|string|max:100',

                // Social Media
                'social_facebook' => 'nullable|url|max:255',
                'social_instagram' => 'nullable|url|max:255',
                'social_twitter' => 'nullable|url|max:255',
                'social_linkedin' => 'nullable|url|max:255',
                'social_youtube' => 'nullable|url|max:255',
                'social_tiktok' => 'nullable|url|max:255',
                'social_pinterest' => 'nullable|url|max:255',

                // Policies
                'policy_privacy' => 'nullable|string',
                'policy_terms' => 'nullable|string',
                'policy_return' => 'nullable|string',
                'policy_shipping' => 'nullable|string',

                // Checkout Options (kept for legacy full-form requests)
                'checkout_allow_guest' => 'nullable|boolean',
                'checkout_require_account' => 'nullable|boolean',
            ];

            $sectionFields = [
                'details' => ['store_name', 'store_description', 'business_registration', 'vat_number', 'store_license', 'store_timezone'],
                'contact' => ['store_email', 'support_email', 'store_phone', 'store_phone_alt', 'store_whatsapp', 'store_address_1', 'store_address_2', 'store_city', 'store_state', 'store_postal_code', 'store_country'],
                'social' => ['social_facebook', 'social_instagram', 'social_twitter', 'social_linkedin', 'social_youtube', 'social_tiktok', 'social_pinterest'],
                'policies' => ['policy_privacy', 'policy_terms', 'policy_return', 'policy_shipping'],
            ];

            // Validate only the active tab so unrelated required fields in hidden tabs
            // cannot block a save. Requests without a section retain full-form validation.
            if (is_string($section) && isset($sectionFields[$section])) {
                $rules = array_intersect_key($rules, array_flip($sectionFields[$section]));
            }

            $validated = $request->validate($rules);

            if (array_key_exists('support_email', $validated)
                && empty($validated['support_email'])
                && ! empty($validated['store_email'] ?? null)) {
                $validated['support_email'] = $validated['store_email'];
            }

            foreach ($validated as $key => $value) {
                // Determine the appropriate type for each setting
                $type = match(true) {
                    in_array($key, ['checkout_allow_guest', 'checkout_require_account']) => 'boolean',
                    is_bool($value) => 'boolean',
                    is_int($value) => 'integer',
                    is_float($value) => 'float',
                    is_array($value) => 'json',
                    default => 'string',
                };
                
                $this->settingService->set($key, $value, $type, 'store');
            }

            // Keep the storefront identity consistent when the Store Name was part of this save.
            if (array_key_exists('store_name', $validated)) {
                $this->settingService->set('site_name', $validated['store_name'], 'string', 'general');
            }

            return redirect()->route('admin.settings.store.index')->with('success', 'Store configuration saved successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Store configuration save error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save store configuration. Please try again.');
        }
    }
}
