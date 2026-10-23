<?php

namespace Shopmade\Jobs\Models;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shopmade\Jobs\Database\Factories\JobFactory;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Services\JobPhotoStore;

#[UseFactory(JobFactory::class)]
#[Fillable(['customer_id', 'assigned_to', 'scheduled_at', 'scheduled_end_at', 'status', 'notes'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    /**
     * The database cascade removes photo rows without firing model events, so delete their files first.
     */
    protected static function booted(): void
    {
        static::deleting(function (Job $job): void {
            $store = app(JobPhotoStore::class);
            $job->photos()->each(fn (JobPhoto $photo) => $store->deleteFiles($photo));
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'status' => JobStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(JobComment::class)->latest();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(JobPhoto::class)->latest();
    }

    public function clientPhotos(): HasMany
    {
        return $this->photos()->where('client_visible', true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [JobStatus::Scheduled, JobStatus::InProgress], true);
    }

    /**
     * The status a crew member advances to with one tap, or null when the job is closed.
     */
    public function nextStatus(): ?JobStatus
    {
        return match ($this->status) {
            JobStatus::Scheduled => JobStatus::InProgress,
            JobStatus::InProgress => JobStatus::Done,
            default => null,
        };
    }

    /**
     * "Tue, Sep 22 · 9:00am–12:00pm" for the visit window, or "Unscheduled".
     */
    public function windowLabel(): string
    {
        if (! $this->scheduled_at) {
            return 'Unscheduled';
        }

        $label = $this->scheduled_at->format('D, M j · g:ia');

        if ($this->scheduled_end_at) {
            $label .= '–'.$this->scheduled_end_at->format('g:ia');
        }

        return $label;
    }
}
