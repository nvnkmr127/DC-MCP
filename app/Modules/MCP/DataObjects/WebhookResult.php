<?php

namespace App\Modules\MCP\DataObjects;

class WebhookResult
{
    public function __construct(
        public readonly bool $isProcessed,
        public readonly string $status, // 'processed', 'processing', 'failed', 'skipped'
        public readonly ?string $errorMessage = null,
        public readonly array $responsePayload = []
    ) {}

    public static function processed(array $responsePayload = []): self
    {
        return new self(true, 'processed', null, $responsePayload);
    }

    public static function processing(array $responsePayload = []): self
    {
        return new self(true, 'processing', null, $responsePayload);
    }

    public static function failed(string $errorMessage, array $responsePayload = []): self
    {
        return new self(false, 'failed', $errorMessage, $responsePayload);
    }

    public static function skipped(string $reason, array $responsePayload = []): self
    {
        return new self(true, 'skipped', $reason, $responsePayload);
    }
}
