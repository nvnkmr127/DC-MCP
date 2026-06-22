<?php

namespace App\Modules\MCP\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class McpProvider extends BaseModel
{
    use SoftDeletes;

    protected $table = 'mcp_providers';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'description',
        'adapter_class',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
