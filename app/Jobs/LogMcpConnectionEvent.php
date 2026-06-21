<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogMcpConnectionEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $connectionId,
        public string $direction,
        public string $entityType,
        public ?string $entityId,
        public string $status,
        public int $processed,
        public int $failed,
        public ?array $payload,
        public ?string $errorMessage,
        public int $durationMs,
        public ?string $userId = null,
        public ?string $idempotencyKey = null
    ) {}

    public function handle(): void
    {
        $metadata = [
            'direction' => $this->direction,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'records_failed' => $this->failed,
            'payload' => $this->payload,
        ];

        DB::table('mcp_sync_logs')->insert([
            'mcp_connection_id' => $this->connectionId,
            'user_id' => $this->userId,
            'idempotency_key' => $this->idempotencyKey,
            'status' => $this->status,
            'duration_ms' => $this->durationMs,
            'records_processed' => $this->processed,
            'bytes_transferred' => 0,
            'error_message' => $this->errorMessage,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
