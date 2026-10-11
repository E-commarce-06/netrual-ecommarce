<?php

namespace Bulbula\Core\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Bulbula\Core\Services\ThemeService;
use Bulbula\Core\Models\Theme;
use Bulbula\UIEditor\Services\LayoutService;
use Bulbula\UIEditor\Models\PageLayout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class ThemeController extends Controller
{
    protected ThemeService $themeService;
    protected LayoutService $layoutService;

    public function __construct(ThemeService $themeService, LayoutService $layoutService)
    {
        $this->themeService = $themeService;
        $this->layoutService = $layoutService;
    }

    /**
     * Display list of all themes
     */
    public function index()
    {
        // Discover new themes from filesystem
        $this->themeService->discover();

        $themes = Theme::all()->map(function ($theme) {
            $config = $theme->getConfig();

            return [
                'id'             => $theme->id,
                'name'           => $theme->name,
                'slug'           => $theme->slug,
                'description'    => $theme->description,
                'version'        => $theme->version,
                'author'         => $theme->author,
                'screenshot'     => $this->resolveThemeScreenshotUrl($theme),
                'is_active'      => $theme->is_active,
                'is_default'     => $theme->is_default,
                'exists'         => $theme->exists(),
                'supports'       => $config['supports'] ?? [],
                'homepageEditor' => $this->homepageEditorMeta($theme),
            ];
        });

        return Inertia::render('Admin/Themes/Index', [
            'themes' => $themes,
        ]);
    }

    /**
     * Unified Appearance page — shows active theme settings in a tabbed layout.
     */
    public function appearance()
    {
        $theme = Theme::where('is_active', true)->first();

        if (!$theme) {
            return redirect()->route('admin.themes.index')
                ->with('error', 'No active theme found. Please activate a theme first.');
        }

        if (!$theme->exists()) {
            return redirect()->route('admin.themes.index')
                ->with('error', 'Active theme files are missing.');
        }

        $schema = $this->themeService->getSettingsSchema($theme);
        $currentSettings = $this->flattenSettings($theme->settings ?? []);
        $dataPath = $theme->getPath() . '/data/theme-data.json';
        $hasThemeData = file_exists($dataPath);
        $hasProductData = false;

        if ($hasThemeData) {
            $themeData = json_decode((string) file_get_contents($dataPath), true);
            $hasProductData = ! empty($themeData['categories']) || ! empty($themeData['products']);
        }

        return Inertia::render('Admin/Appearance/Index', [
            'theme' => [
                'id' => $theme->id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'description' => $theme->description,
                'version' => $theme->version,
                'author' => $theme->author ?? 'Unknown',
                'screenshot' => $this->resolveThemeScreenshotUrl($theme),
                'is_active' => $theme->is_active,
            ],
            'schema' => $schema,
            'settings' => $currentSettings,
            'hasThemeData' => $hasThemeData,
            'hasProductData' => $hasProductData,
            'homepageEditor' => $this->homepageEditorMeta($theme),
        ]);
    }


    /**
     * Redirect to the active theme's settings page.
     * Used by the admin sidebar menu so it always opens the correct theme.
     */
    public function activeSettings()
    {
        return redirect()->route('admin.appearance.index');
    }

    /**
     * Show theme settings page
     */
    public function settings(string $slug)
    {
        return redirect()->route('admin.appearance.index');
    }

    /**
     * Update theme settings
     */
    public function updateSettings(Request $request, string $slug)
    {
        $theme = Theme::where('slug', $slug)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $theme->settings = $request->settings;
            $theme->save();

            // Clear theme cache
            cache()->forget('active_theme');

            return back()->with('success', 'Theme settings updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }






    /**
     * Flatten nested settings into dot-notation keys for the schema-driven form.
     *
     * The theme-data importer writes nested objects (e.g. {"contact": {"phone": "..."}}).
     * The admin form expects flat keys like "contact.phone". This method converts
     * between the two formats automatically so both sources are supported.
     */
    protected function flattenSettings(array $settings, string $prefix = ''): array
    {
        $flat = [];

        foreach ($settings as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value) && !array_is_list($value)) {
                // Recursively flatten associative arrays
                $flat = array_merge($flat, $this->flattenSettings($value, $fullKey));
            } else {
                $flat[$fullKey] = $value;
            }
        }

        return $flat;
    }

    /**
     * Homepage editor affordances differ between UI Editor layouts and native theme homepages.
     *
     * @return array{
     *     mode: string,
     *     showPublishedBanner: bool,
     *     showImportLayout: bool,
     *     editorUrl: string,
     *     editorLabel: string,
     *     bannerTitle: string,
     *     bannerDescription: string
     * }
     */
    protected function homepageEditorMeta(Theme $theme): array
    {
        $config = $theme->getConfig();
        $nativeHomepage = ! empty($config['native_homepage']);
        $dataPath = $theme->getPath() . '/data/theme-data.json';
        $hasUiEditorLayout = false;

        if (file_exists($dataPath)) {
            $themeData = json_decode((string) file_get_contents($dataPath), true);
            $hasUiEditorLayout = ! empty($themeData['homepage']);
        }

        $hasPublishedLayout = PageLayout::homepage()->published()->exists();

        if ($nativeHomepage) {
            return [
                'mode' => 'native',
                'showPublishedBanner' => true,
                'showImportLayout' => false,
                'editorUrl' => route('admin.appearance.index', ['tab' => 'features']),
                'editorLabel' => 'Open Page Builder',
                'bannerTitle' => 'Native Homepage is Live',
                'bannerDescription' => 'This theme renders a built-in homepage. Use the Page Builder to customize homepage sections, hero content, and visibility.',
            ];
        }

        return [
            'mode' => 'layout',
            'showPublishedBanner' => $hasUiEditorLayout && $hasPublishedLayout,
            'showImportLayout' => $hasUiEditorLayout && ! $hasPublishedLayout,
            'editorUrl' => url('/admin/content/pages'),
            'editorLabel' => 'Open Page Builder',
            'bannerTitle' => 'Homepage Layout is Live',
            'bannerDescription' => 'Your homepage is rendering a published layout. Open the Page Builder to edit it.',
        ];
    }

    /**
     * Resolve a theme screenshot URL from public assets, auto-publishing from
     * the theme folder when available.
     */
    protected function resolveThemeScreenshotUrl(Theme $theme): ?string
    {
        $configuredScreenshot = $theme->screenshot ?: ($theme->getConfig()['screenshot'] ?? null);

        if (!$configuredScreenshot) {
            return null;
        }

        $publicRelativePath = "templates/{$theme->slug}/{$configuredScreenshot}";
        $publicPath = public_path($publicRelativePath);

        if (!file_exists($publicPath)) {
            $sourcePath = $theme->getPath() . '/' . ltrim($configuredScreenshot, '/');

            if (file_exists($sourcePath)) {
                $targetDirectory = dirname($publicPath);
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                @copy($sourcePath, $publicPath);
            }
        }

        return file_exists($publicPath) ? asset($publicRelativePath) : null;
    }
}
