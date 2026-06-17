<?php

namespace App\Modules\Notifications\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\Notifications\Models\InAppNotification;

class NotificationWebController extends Controller
{
    public function index(Request $request)
    {
        $notifications = InAppNotification::forUser($request->user()->id)
            ->inApp()
            ->orderByDesc('created_at')
            ->paginate(30)
            ->through(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'data'       => $n->data,
                'is_read'    => $n->is_read,
                'read_at'    => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]);

        $unreadCount = InAppNotification::forUser($request->user()->id)
            ->inApp()
            ->unread()
            ->count();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    public function markRead(Request $request, InAppNotification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }
        $notification->markRead();
        return back();
    }

    public function markAllRead(Request $request)
    {
        InAppNotification::forUser($request->user()->id)
            ->inApp()
            ->unread()
            ->update(['status' => 'read', 'read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
