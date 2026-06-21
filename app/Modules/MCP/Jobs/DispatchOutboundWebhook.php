<?php

namespace App\Modules\MCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchOutboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 60, 300]; // exponential backoff on retries

    public function __construct(
        public readonly object $subscription,
        public readonly string $eventType,
        public readonly array $payload
    ) {}

    public function handle(): void
    {
        $body = [
            'event' => $this->eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload
        ];

        $jsonPayload = json_encode($body);
        $signature = hash_hmac('sha256', $jsonPayload, $this->subscription->secret);
        
        $start = microtime(true);
        $status = 0;
        $responseBody = null;

        try {
            $response = Http::withHeaders([
                'X-DC-Signature' => 'sha256=' . $signature,
                'Content-Type' => 'application/json',
                'User-Agent' => 'DC-MCP-Webhook/1.0',
            ])->timeout(10)->post($this->subscription->url, $body);

            $status = $response->status();
            $responseBody = $response->body();

            if ($response->failed()) {
                Log::warning("Outbound webhook failed", [
                    'url' => $this->subscription->url,
                    'status' => $status,
                    'response' => $responseBody
                ]);
            } else {
                Log::info("Outbound webhook delivered", [
                    'url' => $this->subscription->url,
                    'event' => $this->eventType
                ]);
            }
            
        } catch (\Exception $e) {
            $responseBody = $e->getMessage();
            Log::error("Outbound webhook error", [
                'url' => $this->subscription->url,
                'error' => $responseBody
            ]);
        } finally {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            
            \Illuminate\Support\Facades\DB::table('mcp_webhook_delivery_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'subscription_id' => $this->subscription->id,
                'event_type' => $this->eventType,
                'endpoint_url' => $this->subscription->url,
                'request_payload' => $jsonPayload,
                'response_status' => $status,
                'response_body' => $responseBody,
                'duration_ms' => $durationMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // Re-throw if it failed so the queue can backoff and retry
        if ($status < 200 || $status >= 300) {
            throw new \Exception("Webhook delivery failed with status {$status}: {$responseBody}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Notification::route('mail', config('mail.from.address', 'admin@example.com'))
            ->notify(new \App\Notifications\WebhookFailedNotification(
                'Outbound Webhook',
                $this->subscription->url,
                $exception->getMessage(),
                $this->payload
            ));
    }
}
