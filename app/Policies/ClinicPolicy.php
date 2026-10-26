<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Clinic $clinic): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return true;
    }
}
