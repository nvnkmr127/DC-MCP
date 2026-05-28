<?php

namespace App\Shared\Traits;

use App\Modules\Auth\Scopes\OrganizationScope;
use App\Modules\Auth\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasOrganization
{
    /**
     * Boot the organization trait for a model.
     */
    public static function bootHasOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (auth()->check() && empty($model->organization_id)) {
                $user = auth()->user();
                if ($user && $user->organization_id) {
                    $model->organization_id = $user->organization_id;
                }
            }
        });
    }

    /**
     * Get the organization that owns this record.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
