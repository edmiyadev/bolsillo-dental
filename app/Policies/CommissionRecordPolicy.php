<?php

namespace App\Policies;

use App\Models\CommissionRecord;
use App\Models\User;

class CommissionRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CommissionRecord $record): bool
    {
        return $record->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CommissionRecord $record): bool
    {
        return $record->user_id === $user->id;
    }

    public function delete(User $user, CommissionRecord $record): bool
    {
        return $record->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
