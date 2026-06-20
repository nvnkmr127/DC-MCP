<?php

namespace App\Modules\MCP\DataObjects;

class SyncResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly int $processedCount = 0,
        public readonly int $failedCount = 0,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = [],
        public readonly ?int $durationMs = null,
        public readonly int $bytesTransferred = 0,
        public readonly ?int $expectedCount = null,
        public readonly bool $hasAnomaly = false
    ) {}

    public static function success(int $processedCount = 0, array $metadata = [], ?int $durationMs = null, int $bytesTransferred = 0, ?int $expectedCount = null, bool $hasAnomaly = false): self
    {
        return new self(true, $processedCount, 0, null, $metadata, $durationMs, $bytesTransferred, $expectedCount, $hasAnomaly);
    }

    public static function failure(string $errorMessage, int $processedCount = 0, int $failedCount = 0, array $metadata = [], ?int $durationMs = null, int $bytesTransferred = 0, ?int $expectedCount = null, bool $hasAnomaly = false): self
    {
        return new self(false, $processedCount, $failedCount, $errorMessage, $metadata, $durationMs, $bytesTransferred, $expectedCount, $hasAnomaly);
    }
}
