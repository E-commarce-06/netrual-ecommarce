<?php

declare(strict_types=1);

namespace Bulbula\UIEditor\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Bulbula\CMS\Models\Page;
use Bulbula\UIEditor\Models\PageLayout;
use Bulbula\UIEditor\Services\LayoutService;

class HomepageEditorController extends Controller
{
    public function __construct(
        protected LayoutService $layoutService,
    ) {}

    /**
     * Open the block editor for the homepage.
     *
     * The editor itself is page-based, so redirect to the editor of the
     * CMS page flagged is_homepage. Falls back to the pages list when no
     * homepage page exists yet (older installs before the backfill).
     */
    public function editor(): \Illuminate\Http\RedirectResponse
    {
        $page = Page::homepage()->first();

        if ($page) {
            return redirect()->route('admin.uieditor.pages.editor', $page);
        }

        return redirect('/admin/content/pages');
    }

    /**
     * Save the homepage layout as a draft.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'layout_data'          => 'required|array',
            'layout_data.version'  => 'required|string',
            'layout_data.sections' => 'present|array',
        ]);

        $layout = $this->layoutService->saveDraft(
            $validated['layout_data'],
            PageLayout::TYPE_HOMEPAGE
        );

        return response()->json([
            'status'  => $layout->status,
            'savedAt' => now()->toIso8601String(),
            'message' => 'Draft saved successfully.',
        ]);
    }

    /**
     * Publish the homepage layout.
     */
    public function publish(): JsonResponse
    {
        $layout = $this->layoutService->getHomepage();

        if (! $layout) {
            return response()->json(['message' => 'No homepage layout found to publish.'], 422);
        }

        $layout = $this->layoutService->publish($layout);

        return response()->json([
            'status'      => $layout->status,
            'publishedAt' => $layout->published_at->toIso8601String(),
            'message'     => 'Homepage layout published successfully.',
        ]);
    }

    /**
     * Revert the homepage layout to draft.
     */
    public function unpublish(): JsonResponse
    {
        $layout = $this->layoutService->getHomepage();

        if (! $layout) {
            return response()->json(['message' => 'No homepage layout found.'], 422);
        }

        $layout = $this->layoutService->unpublish($layout);

        return response()->json([
            'status'  => $layout->status,
            'message' => 'Homepage layout unpublished.',
        ]);
    }

    /**
     * Iframe preview of the homepage draft layout.
     */
    public function preview(): Response
    {
        $layout = $this->layoutService->getHomepage();

        return Inertia::render('Admin/UIEditor/Preview', [
            'page'       => ['id' => null, 'title' => 'Homepage'],
            'layoutData' => $layout?->layout_data ?? $this->layoutService->emptyLayout(),
        ]);
    }
}
