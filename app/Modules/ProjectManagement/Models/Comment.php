<?php

namespace App\Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Auth\Models\User;

class Comment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'comments';

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'body',
        'mentions',
        'is_internal',
    ];

    protected $casts = [
        'mentions'    => 'array',
        'is_internal' => 'boolean',
    ];

    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
