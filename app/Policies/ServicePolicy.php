<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_services') || $user->can('view_all_services');
    }

    public function view(User $user, Service $service): bool
    {
        if ($user->can('view_all_services')) {
            return true;
        }

        return $user->can('view_services') && $service->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create_services');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can('update_services')
            && ($service->created_by === $user->id || $user->can('view_all_services'));
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->can('delete_services')
            && ($service->created_by === $user->id || $user->can('view_all_services'));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_services');
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->can('restore_services')
            && ($service->created_by === $user->id || $user->can('view_all_services'));
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $user->can('force_delete_services')
            && ($service->created_by === $user->id || $user->can('view_all_services'));
    }
}
