<?php

namespace App\Modules\MCP\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class McpDeadLetterQueue extends Model
{
    use HasUuids;

    protected $table = 'mcp_dead_letter_queues';

    protected $fillable = [
        'mcp_connection_id',
        'provider',
        'error_message',
        'exception_trace',
        'payload',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
    ];
}
