<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Notifications\Emails\NotificationMail;
use App\Modules\Notifications\Events\NotificationBroadcast;
use App\Modules\MCP\Adapters\ZohoCliqAdapter;
use App\Modules\MCP\Models\McpConnection;
use Illuminate\Support\Facades\DB;
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
                    // Suppress mail config/connection issues
                }
                break;

            case 'zoho_cliq':
                try {
                    $adapter = app(ZohoCliqAdapter::class);
                    $connection = McpConnection::where('organization_id', $user->organization_id)
                        ->where('provider', 'zoho_cliq')
                        ->where('status', 'active')
                        ->first();

                    if ($connection) {
                        $adapter->push($connection->id, [
                            'entity_type' => 'channel_message',
                            'channel' => 'users/' . $user->email,
                            'message' => "{$title}\n\n{$body}"
                        ]);
                    }
                } catch (\Exception $e) {
                    // Suppress Zoho Cliq adapter issues
                }
                break;

            case 'whatsapp':
                try {
                    Http::post(config('services.whatsapp.webhook_url', 'https://api.whatsapp.mock/send'), [
                        'phone' => $user->phone,
                        'message' => "{$title}\n\n{$body}",
                    ]);
                } catch (\Exception $e) {
                    // Suppress connection issues
                }
                break;

            case 'push':
                try {
                    event(new NotificationBroadcast($user->id, $title, $body, $data));
                } catch (\Exception $e) {
                    // Suppress broadcasting driver issues
                }
                break;

            case 'in_app':
            default:
                // Already written to notifications_log table
                break;
        }
    }
}
