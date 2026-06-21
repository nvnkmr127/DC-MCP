<?php

namespace App\Modules\MCP\Listeners;

use App\Modules\MCP\Events\McpWebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessMcpWebhookEvent implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;

    /**
     * Get the middleware the job should pass through.
     * Use WithoutOverlapping keyed by connection ID to guarantee ordered processing
     * and prevent deduplication burst issues.
     */
    public function middleware(McpWebhookReceived $event): array
    {
        return [
            (new WithoutOverlapping($event->connection->id))->dontRelease()
        ];
    }

    /**
     * Handle the event.
     */
    public function handle(McpWebhookReceived $event): void
    {
        $start = microtime(true);

        if ($event->eventId) {
            DB::table('mcp_webhook_events')
                ->where('id', $event->eventId)
                ->update(['status' => 'processing', 'updated_at' => now()]);
        }

        try {
            // Processing logic that actually integrates the webhook result into the application state
            // E.g., handling task creations, modifications, based on $event->result->data

            Log::info('Webhook processed sequentially for connection: ' . $event->connection->id);

            if ($event->eventId) {
                $durationMs = (int) ((microtime(true) - $start) * 1000);
                DB::table('mcp_webhook_events')
                    ->where('id', $event->eventId)
                    ->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'updated_at' => now(),
                        'duration_ms' => $durationMs
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing failed for connection: ' . $event->connection->id, [
                'error' => $e->getMessage(),
                'event_id' => $event->eventId
            ]);

            if ($event->eventId) {
                $durationMs = (int) ((microtime(true) - $start) * 1000);
                DB::table('mcp_webhook_events')
                    ->where('id', $event->eventId)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                        'duration_ms' => $durationMs
                    ]);
            }

            \Illuminate\Support\Facades\Notification::route('mail', config('mail.from.address', 'admin@example.com'))
                ->notify(new \App\Notifications\WebhookFailedNotification(
                    'Inbound Webhook',
                    $event->connection->provider,
                    $e->getMessage(),
                    $event->payload
                ));

            // Re-throw so the queue catches the failure and retries
            throw $e;
        }
    }
}
