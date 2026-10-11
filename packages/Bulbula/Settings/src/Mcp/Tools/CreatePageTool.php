<?php

declare(strict_types=1);

namespace Bulbula\Settings\Mcp\Tools;

use Bulbula\CMS\Services\PageService;
use Bulbula\Settings\Mcp\Contracts\McpToolInterface;
use Bulbula\Settings\Mcp\Support\McpResponse;
use Bulbula\UIEditor\Models\PageLayout;
use Bulbula\UIEditor\Services\LayoutService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreatePageTool implements McpToolInterface
{
    public function __construct(
        protected PageService $pageService,
        protected LayoutService $layouts
    ) {}

    public function name(): string
    {
        return 'bulbula_create_page';
    }

    public function description(): string
    {
        return ('Create a CMS page and initialize an empty ' . config('brand.name') . ' UI Editor layout draft so it can be edited as a template page.');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'url_key' => [
                    'type' => 'string',
                    'description' => 'Optional URL slug; auto-generated from title when omitted',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published', 'disabled'],
                ],
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'meta_keywords' => ['type' => 'string'],
                'with_empty_layout' => [
                    'type' => 'boolean',
                    'description' => 'Create an empty UI Editor draft (default true)',
                ],
            ],
            'required' => ['title'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): array
    {
        try {
            $validated = Validator::make($arguments, [
                'title' => 'required|string|max:255',
                'url_key' => 'nullable|string|max:255',
                'status' => 'nullable|in:draft,published,disabled',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:500',
                'meta_keywords' => 'nullable|string|max:255',
                'with_empty_layout' => 'nullable|boolean',
            ])->validate();
        } catch (ValidationException $e) {
            return McpResponse::error(json_encode($e->errors()));
        }

        $withLayout = array_key_exists('with_empty_layout', $validated)
            ? (bool) $validated['with_empty_layout']
            : true;
        unset($validated['with_empty_layout']);

        $validated['status'] = $validated['status'] ?? 'draft';

        $page = $this->pageService->create($validated);

        $layout = null;
        if ($withLayout && ! $page->is_homepage) {
            $layout = $this->layouts->saveDraft(
                $this->layouts->emptyLayout(),
                PageLayout::TYPE_CMS_PAGE,
                $page->id
            );
        }

        return McpResponse::text([
            'message' => 'Page created',
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'url_key' => $page->url_key,
                'status' => $page->status,
                'editor_url' => url('/admin/uieditor/pages/'.$page->id.'/editor'),
            ],
            'layout' => $layout ? [
                'id' => $layout->id,
                'status' => $layout->status,
            ] : null,
            'hint' => ('Use bulbula_update_page_layout with layout_data (version + sections) to edit this page via the ' . config('brand.name') . ' template editor schema. Use bulbula_list_editor_blocks for available block types.'),
        ]);
    }
}
