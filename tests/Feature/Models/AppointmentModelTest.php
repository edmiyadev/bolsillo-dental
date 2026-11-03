<?php

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Cubicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('automatically calculates ends_at from starts_at and duration_minutes and supports soft deletes', function (): void {
    $appointment = Appointment::factory()->create([
        'starts_at' => '2026-11-02 09:00:00',
        'duration_minutes' => 45,
    ]);

    expect($appointment->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 09:45:00')
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->status->occupiesTimeSlot())->toBeTrue()
        ->and(AppointmentStatus::Cancelled->occupiesTimeSlot())->toBeFalse()
        ->and(AppointmentStatus::NoShow->occupiesTimeSlot())->toBeFalse();

    $appointment->delete();
    expect(Appointment::count())->toBe(0)
        ->and(Appointment::withTrashed()->count())->toBe(1);
});

it('checks cubicle availability against specialty_types and exclusive specialty_schedules', function (): void {
    $clinic = Clinic::factory()->create();
    $branch = Branch::factory()->main()->create(['clinic_id' => $clinic->id]);

    $cubicle = Cubicle::factory()->create([
        'branch_id' => $branch->id,
        'status' => CubicleStatus::Active,
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
        'specialty_schedules' => [
            [
                'day' => DayOfWeek::Tuesday->value,
                'specialty' => DentalSpecialty::Orthodontics->value,
                'start_time' => '09:00',
                'end_time' => '12:00',
            ],
        ],
    ]);

    expect($cubicle->isAvailableForSpecialtyAt(DentalSpecialty::Orthodontics, DayOfWeek::Tuesday, '09:30', '10:30'))->toBeTrue()
        ->and($cubicle->isAvailableForSpecialtyAt(DentalSpecialty::General, DayOfWeek::Tuesday, '09:30', '10:30'))->toBeFalse()
        ->and($cubicle->isAvailableForSpecialtyAt(DentalSpecialty::General, DayOfWeek::Tuesday, '14:00', '15:00'))->toBeTrue()
        ->and($cubicle->isAvailableForSpecialtyAt(DentalSpecialty::Endodontics, DayOfWeek::Tuesday, '14:00', '15:00'))->toBeFalse();
});
