<?php

namespace Shopmade\Jobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shopmade\Jobs\Database\Factories\JobCommentFactory;

#[UseFactory(JobCommentFactory::class)]
#[Fillable(['job_id', 'user_id', 'body'])]
class JobComment extends Model
{
    /** @use HasFactory<JobCommentFactory> */
    use HasFactory;

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
