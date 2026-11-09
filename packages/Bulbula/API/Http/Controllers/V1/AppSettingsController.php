<?php

namespace Bulbula\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Bulbula\Core\Services\SettingService;

/**
 * AppSettingsController
 *
 * Provides public app configuration for the mobile application,
 * including logos, branding assets and store info.
 *
 * GET /api/v1/app/settings
 */
class AppSettingsController extends Controller
{
    protected SettingService $settingService;

    public function __construct(SettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Return app-level settings needed by the mobile app on boot.
     *
     * This endpoint is public (no auth required) so the app can
     * load branding/config before the user logs in (e.g. auth screen logo).
     */
    public function index(): JsonResponse
    {
        $siteName = trim((string) $this->settingService->get('site_name', ''));
        $storeName = trim((string) $this->settingService->get('store_name', ''));
        $name = $storeName !== '' ? $storeName : $siteName;

        // The mobile auth logo is optional. Fall back to the existing site logo,
        // which is already managed in Admin > Settings > General.
        $siteLogo = $this->assetUrl($this->settingService->get('site_logo'));
        $mobileAuthLogo = $this->assetUrl($this->settingService->get('mobile_auth_logo'));
        $supportEmail = trim((string) $this->settingService->get('support_email', ''));
        if ($supportEmail === '') {
            $supportEmail = trim((string) $this->settingService->get('store_email', ''));
        }
        $storePhone = trim((string) $this->settingService->get('store_phone', ''));
        if ($storePhone === '') {
            $storePhone = trim((string) $this->settingService->get('contact_phone', ''));
        }

        // This is a deliberate public allowlist for the mobile client. Do not
        // add credentials, admin-only settings, or an API-origin override here.
        $mobileConfig = [
            'schema_version' => 1,
            'brand' => [
                'name' => $name,
                'tagline' => (string) $this->settingService->get('site_tagline', ''),
                'description' => (string) $this->settingService->get('store_description', ''),
                'logo_url' => $siteLogo,
                'auth_logo_url' => $mobileAuthLogo ?: $siteLogo,
                'colors' => [
                    'primary' => $this->mobileBrandColor(
                        $this->settingService->get('mobile_primary_color', '#1268C4'),
                        '#1268C4',
                    ),
                    'accent' => $this->mobileBrandColor(
                        $this->settingService->get('mobile_accent_color', '#0C62B8'),
                        '#0C62B8',
                    ),
                ],
            ],
            'support' => [
                'email' => $supportEmail,
                'phone' => $storePhone,
                'whatsapp' => (string) $this->settingService->get('store_whatsapp', ''),
            ],
            'store' => [
                'country' => (string) $this->settingService->get('store_country', ''),
                'timezone' => (string) $this->settingService->get('store_timezone', 'UTC'),
            ],
            'policies' => [
                'privacy' => (string) $this->settingService->get('policy_privacy', ''),
                'terms' => (string) $this->settingService->get('policy_terms', ''),
                'returns' => (string) $this->settingService->get('policy_return', ''),
                'shipping' => (string) $this->settingService->get('policy_shipping', ''),
            ],
        ];
        $revisionPayload = json_encode($mobileConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $mobileConfig['revision'] = hash('sha256', $revisionPayload === false ? '' : $revisionPayload);

        // Preserve the existing flat v1 response fields for current clients.
        $settings = [
            'site_name'        => $siteName,
            'site_tagline'     => (string) $this->settingService->get('site_tagline', ''),
            'site_logo'        => $siteLogo,
            'mobile_auth_logo' => $mobileAuthLogo,
            'store_country'    => (string) $this->settingService->get('store_country', ''),
            'contact_phone'    => (string) $this->settingService->get('contact_phone', ''),
            'admin_email'      => (string) $this->settingService->get('admin_email', ''),
            'meta_title'       => (string) $this->settingService->get('meta_title', ''),
            'meta_description' => (string) $this->settingService->get('meta_description', ''),
            'api_version'      => 'v1',
            'mobile_config'    => $mobileConfig,
        ];

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ])->header('Cache-Control', 'public, max-age=60')
          ->header('ETag', '"' . $mobileConfig['revision'] . '"');
    }

    /**
     * Expose only full six-digit RGB colors; malformed settings fall back safely.
     */
    protected function mobileBrandColor($value, string $fallback): string
    {
        $color = trim((string) $value);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
            return $fallback;
        }

        $channels = [];
        foreach ([1, 3, 5] as $offset) {
            $channel = hexdec(substr($color, $offset, 2)) / 255;
            $channels[] = $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        $contrastWithWhite = 1.05 / ($luminance + 0.05);

        return $contrastWithWhite >= 4.5 ? strtoupper($color) : $fallback;
    }

    /**
     * Convert a storage-relative path to a full public URL.
     * Returns null if the path is empty.
     */
    protected function assetUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        // Only publish absolute HTTPS asset URLs. This endpoint is anonymous,
        // and the mobile client intentionally refuses insecure image sources.
        if (parse_url($path, PHP_URL_SCHEME) !== null) {
            $parts = parse_url($path);
            if ($parts === false
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return null;
            }

            return $path;
        }

        // Generate and validate the public storage URL. A misconfigured
        // non-HTTPS storage URL is omitted instead of exposed to mobile clients.
        $url = Storage::disk('public')->url($path);
        $parts = parse_url($url);
        if ($parts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }
}
