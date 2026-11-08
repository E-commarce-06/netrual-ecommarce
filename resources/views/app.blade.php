@php
    $isAdminArea = request()->is('admin', 'admin/*');
    $initialAppearance = $isAdminArea ? ($appearance ?? 'system') : 'light';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $initialAppearance === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Storefronts are light-only; admin routes may still honor the saved appearance. --}}
        <script>
            (function() {
                const isAdminArea = @json($isAdminArea);
                const appearance = @json($initialAppearance);

                if (!isAdminArea) {
                    document.documentElement.classList.remove('dark');
                    return;
                }

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f8fafc;
                color-scheme: light;
            }

            html.dark {
                background-color: #041735;
                color-scheme: dark;
            }
        </style>

        <title inertia>{{ config('brand.name', 'BNEO') }}</title>

        <link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon32 }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $favicon }}">
        <link rel="apple-touch-icon" sizes="512x512" href="{{ $appleTouchIcon }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @php
            $themeStylesheet = null;
            try {
                $activeTheme = \Bulbula\Core\Models\Theme::active();
                if ($activeTheme) {
                    $publicThemeCss = public_path("templates/{$activeTheme->slug}/css/theme.css");
                    if (file_exists($publicThemeCss)) {
                        $themeStylesheet = asset("templates/{$activeTheme->slug}/css/theme.css") . '?v=' . filemtime($publicThemeCss);
                    }
                }
            } catch (\Throwable $e) {
                // ignore during install/migrations
            }
        @endphp
        @if ($themeStylesheet)
            <link rel="stylesheet" href="{{ $themeStylesheet }}">
        @endif
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        @php
            if (app()->bound('bulbula.hook')) {
                ob_start();
                app('bulbula.hook')->doAction('storefront.body.end');
                echo ob_get_clean();
            }
        @endphp
    </body>
</html>
