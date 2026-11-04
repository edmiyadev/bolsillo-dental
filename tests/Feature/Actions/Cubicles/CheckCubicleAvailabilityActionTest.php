<?php

use App\Actions\Cubicles\CheckCubicleAvailabilityAction;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Cubicle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::factory()->create();
    $this->branch = Branch::factory()->main()->create(['clinic_id' => $this->clinic->id]);
    $this->action = new CheckCubicleAvailabilityAction;
});

it('evaluates availability correctly based on specialty schedules, supported specialties, and cubicle status', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Sillón General y Ortodoncia',
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
        'specialty_schedules' => [
            [
                'day' => DayOfWeek::Thursday->value,
                'specialty' => DentalSpecialty::Orthodontics->value,
                'start_time' => '08:00',
                'end_time' => '18:00',
            ],
        ],
        'status' => CubicleStatus::Active,
    ]);

    // On Thursday at 10:00 (inside reserved schedule for Orthodontics)
    expect($this->action->determineReservedSpecialty($cubicle, DayOfWeek::Thursday, '10:00'))->toBe(DentalSpecialty::Orthodontics)
        ->and($this->action->handle($cubicle, DentalSpecialty::Orthodontics, DayOfWeek::Thursday, '10:00'))->toBeTrue()
        ->and($this->action->handle($cubicle, DentalSpecialty::General, DayOfWeek::Thursday, '10:00'))->toBeFalse();

    // On Thursday outside the reserved window (e.g. 19:00)
    expect($this->action->determineReservedSpecialty($cubicle, DayOfWeek::Thursday, '19:00'))->toBeNull()
        ->and($this->action->handle($cubicle, DentalSpecialty::General, DayOfWeek::Thursday, '19:00'))->toBeTrue()
        ->and($this->action->handle($cubicle, DentalSpecialty::Orthodontics, DayOfWeek::Thursday, '19:00'))->toBeTrue();

    // On Friday at 10:00 (no reserved schedule)
    expect($this->action->determineReservedSpecialty($cubicle, DayOfWeek::Friday, '10:00'))->toBeNull()
        ->and($this->action->handle($cubicle, DentalSpecialty::General, DayOfWeek::Friday, '10:00'))->toBeTrue()
        ->and($this->action->handle($cubicle, DentalSpecialty::Orthodontics, DayOfWeek::Friday, '10:00'))->toBeTrue();

    // Unsupported specialty (e.g. OralSurgery)
    expect($this->action->handle($cubicle, DentalSpecialty::OralSurgery, DayOfWeek::Friday, '10:00'))->toBeFalse();

    // Inactive or maintenance cubicle
    $cubicle->update(['status' => CubicleStatus::Maintenance]);
    expect($this->action->handle($cubicle, DentalSpecialty::Orthodontics, DayOfWeek::Thursday, '10:00'))->toBeFalse()
        ->and($this->action->handle($cubicle, DentalSpecialty::General, DayOfWeek::Friday, '10:00'))->toBeFalse();
});
