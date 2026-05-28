<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The boot function is called when the model is initialized.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically generate UUID v4 for the primary key on creation
        static::creating(function (Model $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            // Set created_by if column exists and user is authenticated
            if (config('database.default') !== 'sqlite' && \Schema::hasColumn($model->getTable(), 'created_by') && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        // Automatically set updated_by on update
        static::updating(function (Model $model) {
            // Set updated_by if column exists and user is authenticated
            if (config('database.default') !== 'sqlite' && \Schema::hasColumn($model->getTable(), 'updated_by') && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}

