<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\CommissionRecord;
use App\Models\User;

class CommissionRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_commission_records');
    }

    public function view(User $user, CommissionRecord $record): bool
    {
        return $user->can('view_commission_records')
            && ($record->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function create(User $user): bool
    {
        return $user->can('create_commission_records');
    }

    public function update(User $user, CommissionRecord $record): bool
    {
        return $user->can('update_commission_records')
            && ($record->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function delete(User $user, CommissionRecord $record): bool
    {
        return $user->can('delete_commission_records')
            && ($record->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_commission_records');
    }

    public function restore(User $user, CommissionRecord $record): bool
    {
        return $user->can('restore_commission_records')
            && ($record->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }

    public function forceDelete(User $user, CommissionRecord $record): bool
    {
        return $user->can('force_delete_commission_records')
            && ($record->user_id === $user->id || $user->hasRole(RoleEnum::Admin->value));
    }
}
