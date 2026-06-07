<?php

namespace App\Modules\ProjectManagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Modules\Auth\Models\User;
use App\Shared\Traits\HasOrganization;

class Attachment extends Model
{
    use HasUuids, HasOrganization;

    protected $table = 'attachments';

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'organization_id',
        'filename',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_path',
        'storage_disk',
        'uploaded_by',
    ];

    protected $appends = ['url', 'original_filename', 'size'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        $disk = Storage::disk($this->storage_disk);
        assert($disk instanceof \Illuminate\Filesystem\FilesystemAdapter);

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($this->storage_path, now()->addHours(2));
        }

        return $disk->url($this->storage_path);
    }

    public function getOriginalFilenameAttribute(): string
    {
        return $this->original_name;
    }

    public function getSizeAttribute(): int
    {
        return (int) $this->size_bytes;
    }
}
