<?php

namespace App\Actions\Cubicles;

use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Models\Cubicle;

class CheckCubicleAvailabilityAction
{
    public function handle(Cubicle $cubicle, DentalSpecialty|string $specialty, DayOfWeek|string $day, string $time): bool
    {
        if ($cubicle->status !== CubicleStatus::Active) {
            return false;
        }

        if (! $cubicle->supportsSpecialty($specialty)) {
            return false;
        }

        $reservedSpecialty = $this->determineReservedSpecialty($cubicle, $day, $time);

        if ($reservedSpecialty !== null) {
            $specialtyValue = $specialty instanceof DentalSpecialty ? $specialty->value : $specialty;

            return $reservedSpecialty->value === $specialtyValue;
        }

        return true;
    }

    public function determineReservedSpecialty(Cubicle $cubicle, DayOfWeek|string $day, string $time): ?DentalSpecialty
    {
        $dayValue = $day instanceof DayOfWeek ? $day->value : $day;
        $schedules = $cubicle->specialty_schedules ?? [];

        $checkTime = substr($time, 0, 5);

        foreach ($schedules as $schedule) {
            $scheduleDay = $schedule['day'] ?? null;
            if ($scheduleDay !== $dayValue) {
                continue;
            }

            $startTime = substr((string) ($schedule['start_time'] ?? ''), 0, 5);
            $endTime = substr((string) ($schedule['end_time'] ?? ''), 0, 5);

            if ($startTime !== '' && $endTime !== '' && $checkTime >= $startTime && $checkTime < $endTime) {
                $specialty = $schedule['specialty'] ?? null;

                return $specialty instanceof DentalSpecialty
                    ? $specialty
                    : DentalSpecialty::tryFrom((string) $specialty);
            }
        }

        return null;
    }
}
