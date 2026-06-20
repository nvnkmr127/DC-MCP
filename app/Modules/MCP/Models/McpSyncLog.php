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
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'mcp_connection_id');
    }
}
