<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature',
        'organization_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\Organization::class);
    }

    /**
     * Check if a feature is enabled.
     * Checks organization-specific flag first, then falls back to global flag.
     */
    public static function isEnabled(string $feature, $organizationId = null): bool
    {
        // Try to get org-specific flag
        if ($organizationId) {
            $orgFlag = static::where('feature', $feature)
                ->where('organization_id', $organizationId)
                ->first();
                
            if ($orgFlag) {
                return $orgFlag->is_enabled;
            }
        }

        // Fallback to global flag
        $globalFlag = static::where('feature', $feature)
            ->whereNull('organization_id')
            ->first();

        return $globalFlag ? $globalFlag->is_enabled : false;
    }
}
