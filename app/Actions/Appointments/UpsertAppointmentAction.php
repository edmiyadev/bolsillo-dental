<?php

namespace App\Actions\Appointments;

use App\Data\Appointments\CreateAppointmentData;
use App\Data\Appointments\UpdateAppointmentData;
use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Models\Appointment;
use App\Models\Cubicle;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertAppointmentAction
{
    /**
     * @param  CreateAppointmentData|UpdateAppointmentData|array<string, mixed>  $data
     */
    public function handle(CreateAppointmentData|UpdateAppointmentData|array $data, ?Appointment $appointment = null): Appointment
    {
        $payload = $data instanceof CreateAppointmentData || $data instanceof UpdateAppointmentData
            ? $data->toArray()
            : $data;

        return DB::transaction(function () use ($payload, $appointment): Appointment {
            $appointment ??= new Appointment;

            if (array_key_exists('branch_id', $payload) && filled($payload['branch_id'])) {
                $appointment->branch_id = (int) $payload['branch_id'];
            }

            if (array_key_exists('patient_id', $payload) && filled($payload['patient_id'])) {
                $appointment->patient_id = (int) $payload['patient_id'];
            }

            if (array_key_exists('dentist_id', $payload) && filled($payload['dentist_id'])) {
                $appointment->dentist_id = (int) $payload['dentist_id'];
            }

            $hasServiceId = array_key_exists('service_id', $payload);
            $hasCustomReason = array_key_exists('custom_reason', $payload);
            $hasIsCustomReason = array_key_exists('is_custom_reason', $payload);

            if ($hasServiceId || $hasCustomReason || $hasIsCustomReason) {
                $rawServiceId = $payload['service_id'] ?? ($appointment->is_custom_reason ? 'other' : $appointment->service_id);
                $isCustom = ($rawServiceId === 'other')
                    || (! empty($payload['is_custom_reason']))
                    || (empty($rawServiceId) && ! empty($payload['custom_reason']));

                if ($isCustom) {
                    $appointment->is_custom_reason = true;
                    $appointment->service_id = null;
                    $appointment->custom_reason = $payload['custom_reason'] ?? $appointment->custom_reason;
                } else {
                    $appointment->is_custom_reason = false;
                    $appointment->service_id = filled($rawServiceId) ? (int) $rawServiceId : null;
                    $appointment->custom_reason = null;
                }
            }

            if (array_key_exists('duration_minutes', $payload) && filled($payload['duration_minutes'])) {
                $appointment->duration_minutes = max(1, (int) $payload['duration_minutes']);
            } elseif (! $appointment->duration_minutes) {
                if ($appointment->service_id) {
                    $service = Service::withoutGlobalScopes()->find($appointment->service_id);
                    $appointment->duration_minutes = (int) ($service?->duration_minutes ?? 30);
                } else {
                    $appointment->duration_minutes = 30;
                }
            }

            if (array_key_exists('starts_at', $payload) && filled($payload['starts_at'])) {
                $appointment->starts_at = $payload['starts_at'] instanceof Carbon
                    ? $payload['starts_at']
                    : Carbon::parse((string) $payload['starts_at']);
            }

            if ($appointment->starts_at) {
                $duration = max(1, (int) ($appointment->duration_minutes ?: 30));
                $appointment->ends_at = Carbon::parse($appointment->starts_at)->copy()->addMinutes($duration);
            }

            if (array_key_exists('cubicle_id', $payload) && filled($payload['cubicle_id'])) {
                $appointment->cubicle_id = (int) $payload['cubicle_id'];
            } elseif ($appointment->branch_id && $appointment->starts_at && $appointment->ends_at) {
                $appointment->cubicle_id = $this->resolveAvailableCubicle($appointment);
            }

            if (array_key_exists('status', $payload)) {
                $appointment->status = $payload['status'] instanceof AppointmentStatus
                    ? $payload['status']
                    : (AppointmentStatus::tryFrom((string) $payload['status']) ?? AppointmentStatus::Scheduled);
            } elseif (! $appointment->status) {
                $appointment->status = AppointmentStatus::Scheduled;
            }

            if (array_key_exists('observations', $payload)) {
                $appointment->observations = $payload['observations'];
            }

            $appointment->save();

            return $appointment;
        });
    }

    protected function resolveAvailableCubicle(Appointment $appointment): int
    {
        $specialty = $appointment->specialty;
        $dayOfWeek = DayOfWeek::from(strtolower($appointment->starts_at->englishDayOfWeek));
        $startTime = $appointment->starts_at->format('H:i');
        $endTime = $appointment->ends_at->format('H:i');

        $activeStatuses = collect(AppointmentStatus::cases())
            ->filter(fn (AppointmentStatus $status): bool => $status->occupiesTimeSlot())
            ->map(fn (AppointmentStatus $status): string => $status->value)
            ->values()
            ->all();

        if ($appointment->cubicle_id) {
            $currentCubicle = Cubicle::query()
                ->where('branch_id', $appointment->branch_id)
                ->where('status', CubicleStatus::Active)
                ->find($appointment->cubicle_id);

            if ($currentCubicle && $currentCubicle->isAvailableForSpecialtyAt($specialty, $dayOfWeek, $startTime, $endTime)) {
                $hasOverlap = Appointment::query()
                    ->where('cubicle_id', $currentCubicle->id)
                    ->whereIn('status', $activeStatuses)
                    ->where('starts_at', '<', $appointment->ends_at)
                    ->where('ends_at', '>', $appointment->starts_at)
                    ->when($appointment->exists, fn ($q) => $q->whereKeyNot($appointment->id))
                    ->exists();

                if (! $hasOverlap) {
                    return $currentCubicle->id;
                }
            }
        }

        $availableCubicle = Cubicle::query()
            ->where('branch_id', $appointment->branch_id)
            ->where('status', CubicleStatus::Active)
            ->get()
            ->first(function (Cubicle $cubicle) use ($specialty, $dayOfWeek, $startTime, $endTime, $activeStatuses, $appointment): bool {
                if (! $cubicle->isAvailableForSpecialtyAt($specialty, $dayOfWeek, $startTime, $endTime)) {
                    return false;
                }

                return Appointment::query()
                    ->where('cubicle_id', $cubicle->id)
                    ->whereIn('status', $activeStatuses)
                    ->where('starts_at', '<', $appointment->ends_at)
                    ->where('ends_at', '>', $appointment->starts_at)
                    ->when($appointment->exists, fn ($q) => $q->whereKeyNot($appointment->id))
                    ->doesntExist();
            });

        if (! $availableCubicle) {
            throw ValidationException::withMessages([
                'starts_at' => __('The selected cubicle is not available for the required specialty during this time slot.'),
            ]);
        }

        return $availableCubicle->id;
    }
}
