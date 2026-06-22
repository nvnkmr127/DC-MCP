<?php

namespace App\Modules\MCP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpSyncLog extends Model
{
    protected $fillable = [
        'mcp_connection_id',
        'status',
        'duration_ms',
        'records_processed',
        'bytes_transferred',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted()
    {
        parent::booted();
        static::saving(function ($log) {
            if ($log->isDirty('metadata') && is_array($log->metadata)) {
                $log->metadata = \App\Shared\Helpers\PiiScrubber::scrubArray($log->metadata);
            }
        });
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'mcp_connection_id');
    }
}
