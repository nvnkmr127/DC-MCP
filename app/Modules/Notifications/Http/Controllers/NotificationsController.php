<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('notifications_log')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(25);

        // Decode data JSON for each record
        $notifications->getCollection()->transform(function ($n) {
            $n->data = $n->data ? json_decode($n->data, true) : null;
            return $n;
        });

        return ApiResponse::paginated($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = DB::table('notifications_log')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['count' => $count]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $updated = DB::table('notifications_log')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        if (!$updated) {
            return ApiResponse::error('Notification not found or already read.', [], 404);
        }

        return ApiResponse::success(null, 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        DB::table('notifications_log')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return ApiResponse::success(null, 'All notifications marked as read.');
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $channels = $request->validate([
            'in_app'    => ['sometimes', 'boolean'],
            'email'     => ['sometimes', 'boolean'],
            'push'      => ['sometimes', 'boolean'],
            'zoho_cliq' => ['sometimes', 'boolean'],
            'whatsapp'  => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['notifications'] = array_merge($preferences['notifications'] ?? [], $channels);

        $user->forceFill(['preferences' => $preferences])->save();

        return ApiResponse::success(['notifications' => $preferences['notifications']], 'Preferences updated.');
    }
}
