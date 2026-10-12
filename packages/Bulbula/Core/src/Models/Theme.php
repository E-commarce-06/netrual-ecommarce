<?php

namespace Bulbula\Core\Models;

use Bulbula\Core\Services\ThemePathResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Theme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'catalog_slug',
        'description',
        'version',
        'author',
        'author_url',
        'screenshot',
        'is_active',
        'is_default',
        'source',
        'category',
        'installed_from_catalog_at',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
        'installed_from_catalog_at' => 'datetime',
    ];

    /**
     * Get theme settings
     */
    public function themeSettings(): HasMany
    {
        return $this->hasMany(ThemeSetting::class);
    }

    /**
     * The active theme is chosen by config (theme.default, from BRAND_DEFAULT_THEME),
     * not by a runtime flag. There is one storefront theme; no admin UI changes it.
     */
    public static function active(): ?self
    {
        return static::where('slug', (string) config('theme.default'))->first();
    }

    /**
     * Get theme path under templates/storefront/{category}/{slug}
     */
    public function getPath(): string
    {
        $resolver = app(ThemePathResolver::class);

        $path = $resolver->resolve($this->slug);

        if ($path !== null) {
            return $path;
        }

        return $resolver->resolveOrFail($this->slug);
    }

    /**
     * Check if theme package exists on disk
     */
    public function exists(): bool
    {
        return app(ThemePathResolver::class)->isInstalled($this->slug, $this->category);
    }

    /**
     * Get theme config
     */
    public function getConfig(): array
    {
        $configPath = $this->getPath().'/theme.json';

        if (file_exists($configPath)) {
            return json_decode(file_get_contents($configPath), true) ?? [];
        }

        return [];
    }

    /**
     * Get theme setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set theme setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Get theme asset URL
     */
    public function asset(string $path): string
    {
        return app(ThemePathResolver::class)->publicAssetUrl($this->slug, $path);
    }

    /**
     * Get theme view path
     */
    public function viewPath(string $view): string
    {
        return "themes.{$this->slug}.resources.views.{$view}";
    }
}
