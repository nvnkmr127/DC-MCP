<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConnectedAccount extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'expires_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
