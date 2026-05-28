<?php

namespace App\Modules\Notifications\Jobs;

use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPendingNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function handle(NotificationService $notificationService): void
    {
        // Find notifications that are pending (were queued during quiet hours)
        $pending = DB::table('notifications_log')
            ->where('status', 'pending')
            ->whereNotNull('user_id')
            ->limit(100)
            ->get();

        foreach ($pending as $notification) {
            try {
                $user = User::find($notification->user_id);
                if (!$user) {
                    DB::table('notifications_log')
                        ->where('id', $notification->id)
                        ->update(['status' => 'failed']);
                    continue;
                }

                $data = $notification->data ? json_decode($notification->data, true) : [];

                // Dispatch to channel
                $notificationService->sendNotification(
                    $user,
                    $notification->type,
                    $notification->channel,
                    $notification->title,
                    $notification->body,
                    $data
                );

                // Mark original as superseded (new entry will be created by sendNotification)
                DB::table('notifications_log')
                    ->where('id', $notification->id)
                    ->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (\Exception $e) {
                Log::warning("Failed to send pending notification {$notification->id}: " . $e->getMessage());
                DB::table('notifications_log')
                    ->where('id', $notification->id)
                    ->update(['status' => 'failed']);
            }
        }
    }
}
