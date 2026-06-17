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
        public int $durationMs
    ) {}

    public function handle(): void
    {
        DB::table('mcp_sync_logs')->insert([
            'id' => (string) Str::uuid(),
            'mcp_connection_id' => $this->connectionId,
            'direction' => $this->direction,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'status' => $this->status,
            'records_processed' => $this->processed,
            'records_failed' => $this->failed,
            'payload' => $this->payload ? json_encode($this->payload) : null,
            'error_message' => $this->errorMessage,
            'duration_ms' => $this->durationMs,
            'synced_at' => now(),
        ]);
    }
}
