<?php

namespace App\Http\Middleware;

use Bulbula\Core\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance', 'system');
        if (! in_array($appearance, ['light', 'dark', 'system'], true)) {
            $appearance = 'system';
        }

        // Honor the same saved/system appearance on both the admin and storefront.
        View::share('appearance', $appearance);

        $storedFavicon = $this->settingService->get('site_favicon');
        $faviconUrl = $storedFavicon
            ? Storage::disk('public')->url($storedFavicon)
            : config('brand.favicon', '/logos/bneo-favicon-64.png');

        $favicon32Url = $storedFavicon ? $faviconUrl : config('brand.favicon_32', $faviconUrl);
        $appleTouchIconUrl = $storedFavicon
            ? $faviconUrl
            : config('brand.logo_icon', '/logos/bneo-app-icon.png');

        View::share('favicon', $faviconUrl);
        View::share('favicon32', $favicon32Url);
        View::share('appleTouchIcon', $appleTouchIconUrl);

        return $next($request);
    }
}
