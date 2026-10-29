<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_branches');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->can('view_branches');
    }

    public function create(User $user): bool
    {
        return $user->can('create_branches');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can('update_branches');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can('delete_branches')
            && $branch->clinic
            && $branch->clinic->branches()->count() > 1;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_branches');
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $user->can('restore_branches');
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return $user->can('force_delete_branches')
            && $branch->clinic
            && $branch->clinic->branches()->count() > 1;
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
