<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $announcements = Announcement::where('organization_id', $orgId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('author:id,name')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'is_pinned'    => $a->is_pinned,
                'published_at' => $a->published_at?->toISOString(),
                'expires_at'   => $a->expires_at?->toDateString(),
                'created_at'   => $a->created_at->toISOString(),
                'author'       => ['id' => $a->author?->id, 'name' => $a->author?->name],
            ]);

        return Inertia::render('HR/Announcements/Index', [
            'announcements' => $announcements,
            'canPost'       => in_array($request->user()->role, ['ceo', 'project_manager']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:255',
            'body'       => 'required|string',
            'is_pinned'  => 'boolean',
            'expires_at' => 'nullable|date|after:today',
        ]);

        Announcement::create([
            'organization_id' => $request->user()->organization_id,
            'author_id'       => $request->user()->id,
            'published_at'    => now(),
            ...$validated,
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->organization_id !== $request->user()->organization_id, 403);

        $validated = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'body'       => 'sometimes|string',
            'is_pinned'  => 'sometimes|boolean',
            'expires_at' => 'nullable|date',
        ]);

        $announcement->update($validated);
        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->organization_id !== $request->user()->organization_id, 403);
        $announcement->delete();
        return back()->with('success', 'Announcement deleted.');
    }
}
