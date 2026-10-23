<?php

namespace Shopmade\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shopmade\Jobs\Database\Factories\JobPhotoFactory;
use Shopmade\Jobs\Enums\JobPhotoKind;

#[UseFactory(JobPhotoFactory::class)]
#[Fillable(['job_id', 'user_id', 'path', 'thumb_path', 'original_name', 'mime', 'size', 'kind', 'caption', 'client_visible'])]
class JobPhoto extends Model
{
    /** @use HasFactory<JobPhotoFactory> */
    use HasFactory;

    public const string DISK = 'local';

    protected function casts(): array
    {
        return [
            'kind' => JobPhotoKind::class,
            'client_visible' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return route('jobs.photos.file', ['photo' => $this, 'variant' => 'original']);
    }

    public function thumbUrl(): string
    {
        return $this->thumb_path ? route('jobs.photos.file', ['photo' => $this, 'variant' => 'thumb']) : $this->url();
    }
}
