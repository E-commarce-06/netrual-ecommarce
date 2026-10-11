<?php

declare(strict_types=1);

namespace Bulbula\Blog\Http\Controllers;

use Bulbula\Blog\Models\BlogCategory;
use Bulbula\Blog\Models\BlogPost;
use Bulbula\Core\Services\ThemeViewResolver;
use Inertia\Inertia;
use Inertia\Response;

class BlogController
{
    public function __construct(private ThemeViewResolver $themeResolver) {}

    public function index(): Response
    {
        $posts = BlogPost::published()
            ->with(['category', 'creator'])
            ->orderByDesc('published_at')
            ->paginate(12);

        $categories = BlogCategory::where('status', 'active')
            ->withCount(['posts' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('name')
            ->get();

        return Inertia::render($this->themeResolver->resolve('Blog/Index'), [
            'posts'      => $posts,
            'categories' => $categories,
        ]);
    }

    public function show(string $slug): Response
    {
        $post = BlogPost::published()
            ->where('slug', $slug)
            ->with(['category', 'creator'])
            ->firstOrFail();

        $post->increment('view_count');

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when($post->blog_category_id, fn ($q) => $q->where('blog_category_id', $post->blog_category_id))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return Inertia::render($this->themeResolver->resolve('Blog/Show'), [
            'post'    => $post,
            'related' => $related,
        ]);
    }
}
