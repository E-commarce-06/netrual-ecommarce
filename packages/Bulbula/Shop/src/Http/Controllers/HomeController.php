<?php

namespace Bulbula\Shop\Http\Controllers;

use Bulbula\Shop\Services\HomeService;
use Bulbula\Core\Services\ThemeViewResolver;
use Bulbula\Core\Services\SettingService;
use Bulbula\Core\Models\Theme;
use Bulbula\UIEditor\Services\LayoutService;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * @var HomeService
     */
    protected $homeService;
    
    /**
     * @var ThemeViewResolver
     */
    protected $themeResolver;

    /**
     * @var SettingService
     */
    protected $settingService;

    /**
     * @var LayoutService
     */
    protected $layoutService;

    /**
     * Create a new controller instance.
     *
     * @param HomeService $homeService
     * @param ThemeViewResolver $themeResolver
     */
    public function __construct(HomeService $homeService, ThemeViewResolver $themeResolver, SettingService $settingService, LayoutService $layoutService)
    {
        $this->homeService = $homeService;
        $this->themeResolver = $themeResolver;
        $this->settingService = $settingService;
        $this->layoutService = $layoutService;
    }

    /**
     * Display the homepage.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $data = $this->homeService->getHomepageData();
        $homepageSettings = $this->homepageSettings();
        $homepageLayout = $this->usesNativeHomepage()
            ? null
            : $this->layoutService->getPublishedHomepage();
        $siteName = $this->settingService->get('site_name') ?: config('brand.name');
        $siteDescription = $this->settingService->get('site_tagline') ?: config('brand.tagline');
        
        // Use ThemeViewResolver to get correct view path
        return Inertia::render($this->themeResolver->resolve('Home/Index'), [
            'homepageSettings' => $homepageSettings,
            // 'theme' prop is shared via HandleInertiaRequests middleware (flattened settings)
            'featuredProducts' => $data['featured_products'],
            'heroProducts' => $data['hero_products'] ?? [],
            'offerProducts' => $data['offer_products'] ?? [],
            'bannerProduct' => $data['banner_product'] ?? null,
            'featuredCategories' => $data['categories'], // Renamed for clarity
            'newProducts' => $data['new_products'],
            'categories' => $data['categories'], // Keep for backward compatibility
            'cmsBlocks' => $data['blocks'] ?? [],
            'layoutData' => $homepageLayout?->layout_data,
            'siteConfig' => [
                'name' => $siteName,
                'url' => config('app.url'),
                'description' => $siteDescription,
                'logo' => $this->settingService->get('site_logo') ?? null,
                'favicon' => $this->settingService->get('site_favicon') ?? null,
            ],
            'seo' => [
                'title' => $siteName . ' — Shop online',
                'description' => $homepageSettings['hero_description'] ?? $siteDescription,
                'keywords' => 'online store, products, shopping',
            ],
        ]);
    }

    protected function homepageSettings(): array
    {
        $defaults = (array) config('shop.homepage.content_defaults', []);
        $saved = $this->settingService->get('homepage_content', []);

        return array_replace($defaults, is_array($saved) ? $saved : []);
    }

    protected function usesNativeHomepage(): bool
    {
        $theme = Theme::active();

        if (! $theme) {
            return false;
        }

        return (bool) ($theme->getConfig()['native_homepage'] ?? false);
    }
}
