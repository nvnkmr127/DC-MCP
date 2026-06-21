<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Emails\NotificationMail;
use App\Modules\Notifications\Events\NotificationBroadcast;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification to a user.
     */
    public function sendNotification(User $user, string $type, string $channel, string $title, string $body, ?array $data = null): void
    {
        // 1. Check user preferences
        $preferences = $user->preferences ?? [];
        $enabledChannels = $preferences['notifications'] ?? [];
        // Default to enabled if not explicitly set to false
        $isChannelEnabled = $enabledChannels[$channel] ?? true;

        if (!$isChannelEnabled) {
            return;
        }

        // 2. Check quiet hours: 22:00 to 07:00 Asia/Kolkata
        $now = now()->setTimezone('Asia/Kolkata');
        $hour = $now->hour;
        $isQuietHours = ($hour >= 22 || $hour < 7);

        // System alerts on in_app channel are delivered immediately.
        // Other channels are paused/pending during quiet hours.
        $status = 'sent';
        if ($isQuietHours && $channel !== 'in_app') {
            $status = 'pending';
        }

        // Handle Grouping for in_app notifications
        if ($channel === 'in_app' && isset($data['group_key'])) {
            $existing = DB::table('notifications_log')
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->whereJsonContains('data->group_key', $data['group_key'])
                ->orderByDesc('created_at')
                ->first();

            if ($existing) {
                $existingData = json_decode($existing->data, true) ?? [];
                $count = ($existingData['group_count'] ?? 1) + 1;
                
                $data = array_merge($existingData, $data);
                $data['group_count'] = $count;
                
                $title = isset($data['group_title']) ? str_replace('{count}', $count, $data['group_title']) : $title;
                $body = isset($data['group_body']) ? str_replace('{count}', $count, $data['group_body']) : $body;

                // Delete old so the new one triggers UI poller with a fresh ID
                DB::table('notifications_log')->where('id', $existing->id)->delete();
            } else {
                $data['group_count'] = 1;
            }
        }

        // 3. Log the notification in database
        $notificationId = (string) Str::uuid();
        DB::table('notifications_log')->insert([
            'id' => $notificationId,
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'type' => $type,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'data' => $data ? json_encode($data) : null,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Send if status is 'sent'
        if ($status === 'sent') {
            $this->dispatchToChannel($user, $channel, $title, $body, $data ?? []);
        }
    }

    /**
     * Dispatch notification to external channels.
     */
    protected function dispatchToChannel(User $user, string $channel, string $title, string $body, array $data): void
    {
        switch ($channel) {
            case 'email':
                try {
                    Mail::to($user->email)->send(new NotificationMail($title, $body));
                } catch (\Exception $e) {
                    Log::error('Notification email delivery failed', [
                        'user_id'   => $user->id,
                        'exception' => $e->getMessage(),
                    ]);
                    $this->markLatestFailed($user->id, $channel);
                }
                break;

            case 'zoho_cliq':
                try {
                    $adapter    = app(ZohoCliqAdapter::class);
                    $connection = McpConnection::where('organization_id', $user->organization_id)
                        ->where('provider', 'zoho_cliq')
                        ->where('status', 'active')
                        ->first();

                    if ($connection) {
                        \App\Jobs\PushMcpOutboundActionJob::dispatch($connection->id, [
                            'entity_type' => 'channel_message',
                            'channel'     => 'users/' . $user->email,
                            'message'     => "{$title}\n\n{$body}",
                        ], [
                            'idempotency_key' => 'notification_cliq_' . $user->id . '_' . md5($title . $body)
                        ])->onQueue('high');
                    }
                } catch (\Exception $e) {
                    Log::error('Notification Zoho Cliq delivery failed', [
                        'user_id'         => $user->id,
                        'organization_id' => $user->organization_id,
                        'exception'       => $e->getMessage(),
                    ]);
                    $this->markLatestFailed($user->id, $channel);
                }
                break;

            case 'whatsapp':
                try {
                    Http::post(config('services.whatsapp.webhook_url', 'https://api.whatsapp.mock/send'), [
                        'phone'   => $user->phone,
                        'message' => "{$title}\n\n{$body}",
                    ]);
                } catch (\Exception $e) {
                    Log::error('Notification WhatsApp delivery failed', [
                        'user_id'   => $user->id,
                        'exception' => $e->getMessage(),
                    ]);
                    $this->markLatestFailed($user->id, $channel);
                }
                break;

            case 'push':
                try {
                    event(new NotificationBroadcast($user->id, $title, $body, $data));
                } catch (\Exception $e) {
                    Log::error('Notification push broadcast failed', [
                        'user_id'   => $user->id,
                        'exception' => $e->getMessage(),
                    ]);
                    $this->markLatestFailed($user->id, $channel);
                }
                break;

            case 'in_app':
            default:
                // Already written to notifications_log table above
                break;
        }
    }

    private function markLatestFailed(string $userId, string $channel): void
    {
        DB::table('notifications_log')
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', 'sent')
            ->orderByDesc('created_at')
            ->limit(1)
            ->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
