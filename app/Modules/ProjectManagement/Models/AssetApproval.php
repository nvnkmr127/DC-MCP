<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetApproval extends BaseModel
{
    use HasOrganization;

    protected $table = 'asset_approvals';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'submitted_by', 'reviewed_by',
        'title', 'description', 'type', 'asset_url', 'feedback',
        'version', 'status', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'submitted_by');
    }
}
