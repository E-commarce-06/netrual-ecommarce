<?php

declare(strict_types=1);

namespace Bulbula\Settings\Mcp\Tools;

use Bulbula\Core\Models\Currency;
use Bulbula\Core\Models\Locale;
use Bulbula\Core\Services\SettingService;
use Bulbula\Settings\Mcp\Contracts\McpToolInterface;
use Bulbula\Settings\Mcp\Support\McpResponse;

class StoreInfoTool implements McpToolInterface
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function name(): string
    {
        return 'bulbula_store_info';
    }

    public function description(): string
    {
        return ('Get store name, URL, and currency/locale summary from ' . config('brand.name') . ' settings.');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass,
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): array
    {
        $locale = Locale::query()->where('is_default', true)->first();
        $currency = Currency::query()->where('is_default', true)->first();

        return McpResponse::text([
            'store_name' => (string) $this->settingService->get('store_name', $this->settingService->get('site_name', '')),
            'site_name' => (string) $this->settingService->get('site_name', ''),
            'site_tagline' => (string) $this->settingService->get('site_tagline', ''),
            'url' => (string) config('app.url'),
            'store_email' => (string) $this->settingService->get('store_email', ''),
            'store_country' => (string) $this->settingService->get('store_country', ''),
            'store_timezone' => (string) $this->settingService->get('store_timezone', 'UTC'),
            'locale' => $locale ? [
                'code' => $locale->code,
                'name' => $locale->name,
            ] : null,
            'currency' => $currency ? [
                'code' => $currency->code,
                'symbol' => $currency->symbol ?? null,
                'name' => $currency->name,
            ] : null,
            'mcp_enabled' => (bool) $this->settingService->get('mcp.enabled', false),
        ]);
    }
}
