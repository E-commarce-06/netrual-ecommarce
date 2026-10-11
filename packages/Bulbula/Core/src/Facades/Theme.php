<?php

namespace Bulbula\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array discover()
 * @method static \Bulbula\Core\Models\Theme|null active()
 * @method static bool activate(string $slug)
 * @method static bool install(string $zipPath)
 * @method static bool delete(string $slug)
 * @method static array getSettingsSchema(\Bulbula\Core\Models\Theme $theme)
 * @method static void loadAssets(\Bulbula\Core\Models\Theme|null $theme = null)
 *
 * @see \Bulbula\Core\Services\ThemeService
 */
class Theme extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bulbula.theme';
    }
}
