<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_services');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->can('view_services')
            && ($service->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function create(User $user): bool
    {
        return $user->can('create_services');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->can('update_services')
            && ($service->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->can('delete_services')
            && ($service->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function restore(User $user, Service $service): bool
    {
        return $service->user_id === $user->id;
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $service->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_services');
    }

    public function restore(User $user, Service $service): bool
    {
        return $user->can('restore_services')
            && ($service->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function forceDelete(User $user, Service $service): bool
    {
        return $user->can('force_delete_services')
            && ($service->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }
}
