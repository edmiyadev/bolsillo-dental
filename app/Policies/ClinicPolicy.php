<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_clinics');
    }

    public function view(User $user, ?Clinic $clinic = null): bool
    {
        return $user->can('view_clinics');
    }

    public function create(User $user): bool
    {
        return $user->can('create_clinics');
    }

    public function update(User $user, ?Clinic $clinic = null): bool
    {
        return $user->can('update_clinics');
    }

    public function delete(User $user, ?Clinic $clinic = null): bool
    {
        return $user->can('delete_clinics');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_clinics');
    }

    public function restore(User $user, ?Clinic $clinic = null): bool
    {
        return $user->can('restore_clinics');
    }

    public function forceDelete(User $user, ?Clinic $clinic = null): bool
    {
        return $user->can('force_delete_clinics');
    }
}
