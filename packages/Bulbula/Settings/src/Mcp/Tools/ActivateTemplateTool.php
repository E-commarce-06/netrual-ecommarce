<?php

declare(strict_types=1);

namespace Bulbula\Settings\Mcp\Tools;

use Bulbula\Core\Services\ThemeService;
use Bulbula\Settings\Mcp\Contracts\McpToolInterface;
use Bulbula\Settings\Mcp\Support\McpResponse;

class ActivateTemplateTool implements McpToolInterface
{
    public function __construct(
        protected ThemeService $themeService
    ) {}

    public function name(): string
    {
        return 'bulbula_activate_template';
    }

    public function description(): string
    {
        return 'Activate an installed storefront template/theme by slug. Files must exist on disk.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => [
                    'type' => 'string',
                    'description' => 'Template slug to activate',
                ],
            ],
            'required' => ['slug'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): array
    {
        $slug = trim((string) ($arguments['slug'] ?? ''));
        if ($slug === '') {
            return McpResponse::error('slug is required');
        }

        $ok = $this->themeService->activate($slug);
        if (! $ok) {
            return McpResponse::error(
                "Could not activate template \"{$slug}\". Ensure it is installed and files exist on disk."
            );
        }

        $active = $this->themeService->active();

        return McpResponse::text([
            'message' => 'Template activated',
            'slug' => $active?->slug,
            'name' => $active?->name,
            'is_active' => (bool) ($active?->is_active),
        ]);
    }
}
