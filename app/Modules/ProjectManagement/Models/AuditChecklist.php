<?php

namespace App\Modules\ProjectManagement\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditChecklist extends BaseModel
{
    use HasOrganization;

    protected $table = 'audit_checklists';

    protected $fillable = [
        'organization_id', 'client_id', 'project_id', 'asset_approval_id', 'assigned_to', 'title',
        'type', 'items', 'status', 'due_date',
    ];

    protected $casts = [
        'items'    => 'array',
        'due_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assetApproval(): BelongsTo
    {
        return $this->belongsTo(AssetApproval::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'assigned_to');
    }
}
