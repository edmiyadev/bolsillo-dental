<?php

namespace App\Policies;

use App\Models\Cubicle;
use App\Models\User;

class CubiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_cubicles');
    }

    public function view(User $user, ?Cubicle $cubicle = null): bool
    {
        return $user->can('view_cubicles');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cubicles');
    }

    public function update(User $user, ?Cubicle $cubicle = null): bool
    {
        return $user->can('update_cubicles');
    }

    public function delete(User $user, ?Cubicle $cubicle = null): bool
    {
        return $user->can('delete_cubicles');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_cubicles');
    }

    public function restore(User $user, ?Cubicle $cubicle = null): bool
    {
        return $user->can('restore_cubicles');
    }

    public function forceDelete(User $user, ?Cubicle $cubicle = null): bool
    {
        return $user->can('force_delete_cubicles');
    }
}
