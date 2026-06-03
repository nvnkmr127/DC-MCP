<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\KnowledgeArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseWebController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $query = KnowledgeArticle::where('organization_id', $orgId)
            ->where('is_published', true)
            ->with('author:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%"));
        }

        $articles = $query->get()->map(fn($a) => [
            'id'         => $a->id,
            'title'      => $a->title,
            'body'       => $a->body,
            'category'   => $a->category,
            'tags'       => $a->tags ?? [],
            'view_count' => $a->view_count,
            'created_at' => $a->created_at->toDateString(),
            'author'     => ['id' => $a->author?->id, 'name' => $a->author?->name],
        ]);

        $categories = KnowledgeArticle::where('organization_id', $orgId)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('KnowledgeBase/Index', [
            'articles'   => $articles,
            'categories' => $categories,
            'filters'    => $request->only(['category', 'search']),
        ]);
    }

    public function show(KnowledgeArticle $article): Response
    {
        $article->increment('view_count');
        $article->load('author:id,name');
        return Inertia::render('KnowledgeBase/Show', ['article' => $article]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:255',
            'tags'     => 'nullable|array',
        ]);

        KnowledgeArticle::create([
            'organization_id' => $request->user()->organization_id,
            'author_id'       => $request->user()->id,
            ...$validated,
        ]);

        return back()->with('success', 'Article created.');
    }

    public function update(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        $this->authorizeOrg($article);

        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'body'         => 'sometimes|string',
            'category'     => 'nullable|string',
            'tags'         => 'nullable|array',
            'is_published' => 'sometimes|boolean',
        ]);

        $article->update($validated);
        return back()->with('success', 'Article updated.');
    }

    public function destroy(Request $request, KnowledgeArticle $article): RedirectResponse
    {
        $this->authorizeOrg($article);
        $article->delete();
        return back()->with('success', 'Article deleted.');
    }
}
