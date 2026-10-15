<?php

declare(strict_types=1);

namespace Bulbula\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Bulbula\Core\Models\Theme;
use Bulbula\Core\Services\ThemeService;

/**
 * Admin editor for the active theme's settings (colors, typography,
 * layout, feature toggles).
 *
 * The field schema comes from the theme's own config/settings.php and
 * values are stored nested on the themes.settings column. The storefront
 * already flattens + merges these over the theme defaults
 * (ShareFrontendData), so saving here changes the live storefront with
 * no template changes. Before this screen existed the schema was
 * defined but unreachable from the admin panel.
 */
class ThemeSettingsController extends Controller
{
    public function __construct(
        protected ThemeService $themeService,
    ) {}

    public function index(): Response
    {
        $theme = Theme::active();

        if (! $theme) {
            return Inertia::render('Admin/Settings/Theme/Index', [
                'theme'    => null,
                'sections' => [],
                'values'   => (object) [],
            ]);
        }

        $schema = $this->themeService->getSettingsSchema($theme);

        return Inertia::render('Admin/Settings/Theme/Index', [
            'theme'    => [
                'name' => $theme->name,
                'slug' => $theme->slug,
            ],
            'sections' => $schema['sections'] ?? [],
            'values'   => (object) $this->effectiveValues($theme, $schema),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $theme = Theme::active();

        if (! $theme) {
            return back()->with('error', 'No active theme to save settings for.');
        }

        $schema = $this->themeService->getSettingsSchema($theme);
        $input  = (array) $request->input('settings', []);
        $clean  = [];

        foreach ($schema['sections'] ?? [] as $section) {
            $sectionId = $section['id'] ?? null;
            if (! $sectionId) {
                continue;
            }

            foreach ($section['fields'] ?? [] as $field) {
                $fieldId = $field['id'] ?? null;
                if (! $fieldId) {
                    continue;
                }

                $raw = $input[$sectionId][$fieldId] ?? $field['default'] ?? null;

                $clean[$sectionId][$fieldId] = $this->sanitizeField($field, $raw);
            }
        }

        $theme->settings = $clean;
        $theme->save();

        return back()->with('success', 'Theme settings saved.');
    }

    /**
     * Effective values, nested by section → field: schema defaults,
     * overlaid with the theme.json config defaults, overlaid with
     * saved DB settings (themes.settings, also nested).
     */
    private function effectiveValues(Theme $theme, array $schema): array
    {
        $values = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (isset($section['id'], $field['id']) && array_key_exists('default', $field)) {
                    $values[$section['id']][$field['id']] = $field['default'];
                }
            }
        }

        $configDefaults = $theme->getConfig()['settings'] ?? [];
        if (is_array($configDefaults)) {
            $values = array_replace_recursive($values, $configDefaults);
        }

        if (is_array($theme->settings)) {
            $values = array_replace_recursive($values, $theme->settings);
        }

        return $values;
    }

    private function sanitizeField(array $field, mixed $raw): mixed
    {
        return match ($field['type'] ?? 'text') {
            'color' => (is_string($raw) && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($raw)))
                ? strtolower(trim($raw))
                : ($field['default'] ?? '#000000'),

            'select' => (is_string($raw) && isset($field['options']) && array_key_exists($raw, $field['options']))
                ? $raw
                : ($field['default'] ?? null),

            'number' => is_numeric($raw)
                ? $this->clampNumber($field, $raw + 0)
                : ($field['default'] ?? 0),

            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),

            default => is_scalar($raw) ? mb_substr(trim((string) $raw), 0, 255) : ($field['default'] ?? ''),
        };
    }

    private function clampNumber(array $field, int|float $value): int|float
    {
        if (isset($field['min']) && is_numeric($field['min'])) {
            $value = max($field['min'] + 0, $value);
        }
        if (isset($field['max']) && is_numeric($field['max'])) {
            $value = min($field['max'] + 0, $value);
        }

        return $value;
    }

}
