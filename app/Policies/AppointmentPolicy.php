<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_appointments') || $user->can('view_all_appointments');
    }

    public function view(User $user, ?Appointment $appointment = null): bool
    {
        if ($appointment === null) {
            return $this->viewAny($user);
        }

        if ($user->can('view_all_appointments')) {
            return true;
        }

        return $user->can('view_appointments')
            && ($appointment->dentist_id === $user->id || $appointment->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('create_appointments');
    }

    public function update(User $user, ?Appointment $appointment = null): bool
    {
        if ($appointment === null) {
            return $user->can('update_appointments');
        }

        if ($user->can('view_all_appointments')) {
            return $user->can('update_appointments');
        }

        return $user->can('update_appointments')
            && ($appointment->dentist_id === $user->id || $appointment->created_by === $user->id);
    }

    public function delete(User $user, ?Appointment $appointment = null): bool
    {
        if ($appointment === null) {
            return $user->can('delete_appointments');
        }

        if ($user->can('view_all_appointments')) {
            return $user->can('delete_appointments');
        }

        return $user->can('delete_appointments')
            && ($appointment->dentist_id === $user->id || $appointment->created_by === $user->id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_appointments');
    }

    public function restore(User $user, ?Appointment $appointment = null): bool
    {
        if ($appointment === null) {
            return $user->can('restore_appointments');
        }

        if ($user->can('view_all_appointments')) {
            return $user->can('restore_appointments');
        }

        return $user->can('restore_appointments')
            && ($appointment->dentist_id === $user->id || $appointment->created_by === $user->id);
    }

    public function forceDelete(User $user, ?Appointment $appointment = null): bool
    {
        if ($appointment === null) {
            return $user->can('force_delete_appointments');
        }

        if ($user->can('view_all_appointments')) {
            return $user->can('force_delete_appointments');
        }

        return $user->can('force_delete_appointments')
            && ($appointment->dentist_id === $user->id || $appointment->created_by === $user->id);
    }
}
