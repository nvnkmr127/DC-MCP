<?php

namespace App\Modules\Reporting\Models;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use App\Shared\Traits\HasClientScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends BaseModel
{
    use HasOrganization, HasClientScope;

    protected $table = 'report_schedules';

    protected $fillable = [
        'organization_id',
        'project_id',
        'client_id',
        'title',
        'type',
        'template',
        'frequency',
        'send_day',
        'config',
        'recipients',
        'is_active',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectManagement\Models\Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'created_by');
    }
}
