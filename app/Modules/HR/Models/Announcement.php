<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends BaseModel
{
    use HasOrganization;

    protected $table = 'announcements';

    protected $fillable = [
        'organization_id', 'author_id', 'title', 'body',
        'is_pinned', 'published_at', 'expires_at',
    ];

    protected $casts = [
        'is_pinned'    => 'boolean',
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'author_id');
    }
}
