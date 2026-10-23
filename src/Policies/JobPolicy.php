<?php

namespace Shopmade\Jobs\Policies;

use App\Models\User;
use Shopmade\Jobs\Models\Job;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isClient();
    }

    public function view(User $user, Job $job): bool
    {
        return ! $user->isClient() || $this->isMember($user, $job);
    }

    public function create(User $user): bool
    {
        return ! $user->isClient() && $user->canManageCoreTool('jobs');
    }

    public function update(User $user, Job $job): bool
    {
        return ! $user->isClient() && $user->canManageCoreTool('jobs');
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->update($user, $job);
    }

    public function managePhotos(User $user, Job $job): bool
    {
        return $this->update($user, $job);
    }

    public function comment(User $user, Job $job): bool
    {
        return $this->view($user, $job);
    }

    private function isMember(User $user, Job $job): bool
    {
        return $user->customers()->where('customers.id', $job->customer_id)->exists();
    }
}
