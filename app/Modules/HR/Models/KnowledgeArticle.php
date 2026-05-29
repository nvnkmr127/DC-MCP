<?php

namespace App\Modules\HR\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeArticle extends BaseModel
{
    use HasOrganization, SoftDeletes;

    protected $table = 'knowledge_articles';

    protected $fillable = [
        'organization_id', 'author_id', 'title', 'body',
        'category', 'tags', 'is_published', 'view_count',
    ];

    protected $casts = [
        'tags'         => 'array',
        'is_published' => 'boolean',
        'deleted_at'   => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'author_id');
    }
}
