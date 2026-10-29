<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Branch $branch): bool
    {
        return true;
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $branch->clinic && $branch->clinic->branches()->count() > 1;
    }

    public function restore(User $user, Branch $branch): bool
    {
        return true;
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return (bool) ($branch->clinic && $branch->clinic->branches()->count() > 1);
    }
}
