<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebhookFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $webhookType,
        public readonly string $providerOrUrl,
        public readonly string $errorMessage,
        public readonly array $payloadSnippet
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Webhook Delivery Failed: {$this->webhookType}")
            ->line("A critical webhook processing error occurred in DC-MCP.")
            ->line("**Type:** {$this->webhookType}")
            ->line("**Provider / Target:** {$this->providerOrUrl}")
            ->line("**Error Message:** {$this->errorMessage}")
            ->line("**Payload Snippet:**")
            ->line(json_encode(array_slice($this->payloadSnippet, 0, 5)))
            ->action('View Webhook Dashboard', url('/admin/webhooks/dashboard'))
            ->line('Please investigate the issue to ensure data consistency.');
    }
}
