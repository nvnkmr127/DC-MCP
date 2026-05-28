<?php

namespace App\Modules\MCP\DataObjects;

class SyncResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly int $processedCount = 0,
        public readonly int $failedCount = 0,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {}

    public static function success(int $processedCount = 0, array $metadata = []): self
    {
        return new self(true, $processedCount, 0, null, $metadata);
    }

    public static function failure(string $errorMessage, int $processedCount = 0, int $failedCount = 0, array $metadata = []): self
    {
        return new self(false, $processedCount, $failedCount, $errorMessage, $metadata);
    }
}
