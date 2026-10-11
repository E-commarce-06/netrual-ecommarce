<?php

declare(strict_types=1);

namespace Bulbula\Settings\Mcp;

use Bulbula\Settings\Mcp\Contracts\McpToolInterface;
use Bulbula\Settings\Mcp\Tools\CreatePageTool;
use Bulbula\Settings\Mcp\Tools\CreateProductTool;
use Bulbula\Settings\Mcp\Tools\GetOrderTool;
use Bulbula\Settings\Mcp\Tools\GetPageLayoutTool;
use Bulbula\Settings\Mcp\Tools\GetPageTool;
use Bulbula\Settings\Mcp\Tools\GetProductTool;
use Bulbula\Settings\Mcp\Tools\GetSettingsTool;
use Bulbula\Settings\Mcp\Tools\ListCustomersTool;
use Bulbula\Settings\Mcp\Tools\ListEditorBlocksTool;
use Bulbula\Settings\Mcp\Tools\ListOrdersTool;
use Bulbula\Settings\Mcp\Tools\ListPagesTool;
use Bulbula\Settings\Mcp\Tools\ListProductsTool;
use Bulbula\Settings\Mcp\Tools\PublishPageLayoutTool;
use Bulbula\Settings\Mcp\Tools\SearchTool;
use Bulbula\Settings\Mcp\Tools\StoreInfoTool;
use Bulbula\Settings\Mcp\Tools\UpdateOrderStatusTool;
use Bulbula\Settings\Mcp\Tools\UpdatePageLayoutTool;
use Bulbula\Settings\Mcp\Tools\UpdatePageTool;
use Bulbula\Settings\Mcp\Tools\UpdateProductTool;
use Bulbula\Settings\Mcp\Tools\UpdateSettingsTool;
use InvalidArgumentException;

class McpToolRegistry
{
    /** @var array<string, McpToolInterface>|null */
    private ?array $tools = null;

    /**
     * @return list<class-string<McpToolInterface>>
     */
    public function toolClasses(): array
    {
        return [
            // Store / catalog / commerce
            StoreInfoTool::class,
            ListProductsTool::class,
            GetProductTool::class,
            CreateProductTool::class,
            UpdateProductTool::class,
            ListOrdersTool::class,
            GetOrderTool::class,
            UpdateOrderStatusTool::class,
            GetSettingsTool::class,
            UpdateSettingsTool::class,
            ListCustomersTool::class,
            SearchTool::class,

            // Storefront templates (themes)

            // CMS pages + Bulbula UI / template editor
            ListPagesTool::class,
            GetPageTool::class,
            CreatePageTool::class,
            UpdatePageTool::class,
            GetPageLayoutTool::class,
            UpdatePageLayoutTool::class,
            PublishPageLayoutTool::class,
            ListEditorBlocksTool::class,
        ];
    }

    /**
     * @return array<string, McpToolInterface>
     */
    public function all(): array
    {
        if ($this->tools === null) {
            $this->tools = [];
            foreach ($this->toolClasses() as $class) {
                /** @var McpToolInterface $tool */
                $tool = app($class);
                $this->tools[$tool->name()] = $tool;
            }
        }

        return $this->tools;
    }

    public function get(string $name): McpToolInterface
    {
        $tools = $this->all();

        if (! isset($tools[$name])) {
            throw new InvalidArgumentException("Unknown MCP tool: {$name}");
        }

        return $tools[$name];
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function listDefinitions(): array
    {
        return array_values(array_map(function (McpToolInterface $tool) {
            return [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];
        }, $this->all()));
    }
}
