<?php

declare(strict_types=1);

namespace Bulbula\Settings\Mcp\Tools;

use Bulbula\Core\Services\SettingService;
use Bulbula\Settings\Mcp\Contracts\McpToolInterface;
use Bulbula\Settings\Mcp\Support\McpResponse;
use Bulbula\Settings\Mcp\Support\SafeSettings;

class GetSettingsTool implements McpToolInterface
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function name(): string
    {
        return 'bulbula_get_settings';
    }

    public function description(): string
    {
        return 'Get safe general/store settings. Never returns payment, SMTP, or AI secrets. Optional group: general|store.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group' => [
                    'type' => 'string',
                    'enum' => ['general', 'store'],
                    'description' => 'Optional settings group filter',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): array
    {
        $group = isset($arguments['group']) ? (string) $arguments['group'] : null;

        if ($group !== null && $group !== '' && ! isset(SafeSettings::GROUPS[$group])) {
            return McpResponse::error('Invalid group. Allowed: general, store');
        }

        $keys = SafeSettings::keysForGroup($group === '' ? null : $group);
        if ($keys === []) {
            return McpResponse::error('No settings available for that group');
        }

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $this->settingService->get($key);
        }

        return McpResponse::text([
            'group' => $group ?: 'all',
            'settings' => $settings,
        ]);
    }
}
