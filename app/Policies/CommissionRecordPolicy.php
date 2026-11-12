<?php

namespace App\Policies;

use App\Models\CommissionRecord;
use App\Models\User;

class CommissionRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_commission_records') || $user->can('view_all_commission_records');
    }

    public function view(User $user, CommissionRecord $record): bool
    {
        if ($user->can('view_all_commission_records')) {
            return true;
        }

        return $user->can('view_commission_records') && $record->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create_commission_records');
    }

    public function update(User $user, CommissionRecord $record): bool
    {
        return $user->can('update_commission_records')
            && ($record->created_by === $user->id || $user->can('view_all_commission_records'));
    }

    public function delete(User $user, CommissionRecord $record): bool
    {
        return $user->can('delete_commission_records')
            && ($record->created_by === $user->id || $user->can('view_all_commission_records'));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_commission_records');
    }

    public function restore(User $user, CommissionRecord $record): bool
    {
        return $user->can('restore_commission_records')
            && ($record->created_by === $user->id || $user->can('view_all_commission_records'));
    }

    public function forceDelete(User $user, CommissionRecord $record): bool
    {
        return $user->can('force_delete_commission_records')
            && ($record->created_by === $user->id || $user->can('view_all_commission_records'));
    }
}
