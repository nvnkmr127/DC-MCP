<?php

namespace App\Modules\Reporting\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use App\Shared\Traits\HasClientScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends BaseModel
{
    use HasOrganization, HasClientScope, SoftDeletes;

    protected $table = 'reports';

    protected $fillable = [
        'organization_id',
        'project_id',
        'client_id',
        'title',
        'type',
        'status',
        'template',
        'date_from',
        'date_to',
        'config',
        'generated_file_path',
        'generated_at',
        'sent_at',
        'generated_by',
        'recipients',
        'share_token',
        'is_public',
    ];

    protected $casts = [
        'config' => 'array',
        'recipients' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'generated_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(\App\Modules\ProjectManagement\Models\Comment::class, 'commentable');
    }
}
