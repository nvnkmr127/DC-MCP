<?php

namespace App\Modules\Revenue\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverableSubmission extends BaseModel
{
    use HasOrganization;

    protected $table = 'deliverable_submissions';

    protected $fillable = [
        'organization_id', 'sow_deliverable_id', 'submitted_by', 'reviewer_id',
        'file_url', 'external_link', 'notes', 'status',
        'reviewed_at', 'reviewer_notes', 'revision_number',
    ];

    protected $casts = [
        'reviewed_at'     => 'datetime',
        'revision_number' => 'integer',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(SowDeliverable::class, 'sow_deliverable_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'reviewer_id');
    }
}
