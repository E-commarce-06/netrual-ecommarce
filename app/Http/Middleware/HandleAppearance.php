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
        $appearance = $request->cookie('appearance') ?? 'system';
        $forceLightStorefront = false;

        // The storefront theme ships light-only CSS (no dark: variants). When the
        // OS is in dark mode, the dark class and native form controls made text
        // and backgrounds the same colour. Storefront pages are always light.
        if (! $request->is('admin') && ! $request->is('admin/*')) {
            $appearance = 'light';
            $forceLightStorefront = true;
        }

        View::share('appearance', $appearance);
        View::share('forceLightStorefront', $forceLightStorefront);

        $storedFavicon = $this->settingService->get('site_favicon');
        $faviconUrl = $storedFavicon
            ? Storage::disk('public')->url($storedFavicon)
            : '/logos/favicon.png';

        View::share('favicon', $faviconUrl);

        return $next($request);
    }
}
