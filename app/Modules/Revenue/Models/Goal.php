<?php

namespace App\Modules\Revenue\Models;

use Laravel\Scout\Searchable;

use App\Shared\Models\BaseModel;
use App\Shared\Traits\HasOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends BaseModel
{
    use HasOrganization, SoftDeletes, Searchable;

    protected $table = 'goals';

    protected $fillable = [
        'organization_id', 'owner_id', 'title', 'description',
        'period', 'year', 'status', 'progress', 'key_results',
    ];

    protected $casts = [
        'key_results' => 'array',
        'progress'    => 'integer',
        'year'        => 'integer',
    ];

    public function milestones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\ProjectManagement\Models\Milestone::class);
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\ProjectManagement\Models\Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'owner_id');
    }

    public function recalculateProgress(): void
    {
        $krs = $this->key_results ?? [];
        if (empty($krs)) {
            return;
        }
        $total = 0;
        foreach ($krs as $kr) {
            $target  = (float) ($kr['target'] ?? 1);
            $current = (float) ($kr['current'] ?? 0);
            $pct     = $target > 0 ? min(100, ($current / $target) * 100) : 0;
            $total  += $pct;
        }
        $this->progress = (int) round($total / count($krs));
        $this->save();
    }
}
