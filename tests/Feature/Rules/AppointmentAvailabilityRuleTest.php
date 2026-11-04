<?php

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\User;
use App\Rules\AppointmentAvailabilityRule;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->clinic = Clinic::factory()->create();
    $this->branch = Branch::factory()->main()->create([
        'clinic_id' => $this->clinic->id,
        'opening_hours' => DayOfWeek::defaultSchedule(), // Lun-Vie 08:00-18:00, Sab 08:00-13:00, Dom cerrado
    ]);

    $this->dentist = User::factory()->create([
        'specialties' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);
    $this->dentist->assignRole(RoleEnum::Dentist->value);
    $this->dentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    $this->cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => CubicleStatus::Active,
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);

    $this->patient = Patient::factory()->create();
});

it('passes when branch is open, dentist is on shift with specialty, and cubicle is available', function (): void {
    $data = [
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General->value,
        'starts_at' => '2026-11-02 09:00:00', // Monday
        'duration_minutes' => 30,
    ];

    $validator = Validator::make($data, [
        'starts_at' => [new AppointmentAvailabilityRule],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('fails when dentist or cubicle has an overlapping active appointment but ignores cancelled or self', function (): void {
    $existing = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-02 09:00:00',
        'duration_minutes' => 60,
        'status' => AppointmentStatus::Confirmed,
    ]);

    $payload = [
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General->value,
        'starts_at' => '2026-11-02 09:30:00',
        'duration_minutes' => 30,
    ];

    // Overlaps with active appointment
    expect(Validator::make($payload, ['starts_at' => [new AppointmentAvailabilityRule]])->fails())->toBeTrue();

    // Self-ignore when editing the same appointment
    expect(Validator::make($payload, ['starts_at' => [new AppointmentAvailabilityRule($existing->id)]])->passes())->toBeTrue();

    // Cancelled appointment does not block the slot
    $existing->update(['status' => AppointmentStatus::Cancelled]);
    expect(Validator::make($payload, ['starts_at' => [new AppointmentAvailabilityRule]])->passes())->toBeTrue();
});

it('fails when dentist lacks specialty or is outside branch shift hours', function (): void {
    // Endodontics is not in dentist's specialties
    $payloadSpecialty = [
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::Endodontics->value,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 30,
    ];
    expect(Validator::make($payloadSpecialty, ['starts_at' => [new AppointmentAvailabilityRule]])->fails())->toBeTrue();

    // Sunday (branch closed / dentist off shift)
    $payloadSunday = [
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General->value,
        'starts_at' => '2026-11-01 10:00:00', // Sunday
        'duration_minutes' => 30,
    ];
    expect(Validator::make($payloadSunday, ['starts_at' => [new AppointmentAvailabilityRule]])->fails())->toBeTrue();
});

it('fails when cubicle is reserved for another specialty during an exclusive block or when crossing midnight', function (): void {
    $this->cubicle->update([
        'specialty_schedules' => [
            [
                'day' => DayOfWeek::Monday->value,
                'specialty' => DentalSpecialty::Orthodontics->value,
                'start_time' => '09:00',
                'end_time' => '12:00',
            ],
        ],
    ]);

    $payloadConflict = [
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General->value,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 30,
    ];
    expect(Validator::make($payloadConflict, ['starts_at' => [new AppointmentAvailabilityRule]])->fails())->toBeTrue();

    $payloadMidnight = [
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General->value,
        'starts_at' => '2026-11-02 23:30:00',
        'duration_minutes' => 60,
    ];
    expect(Validator::make($payloadMidnight, ['starts_at' => [new AppointmentAvailabilityRule]])->fails())->toBeTrue();
});

it('grants appointment permissions according to role in PermissionSeeder', function (): void {
    $receptionist = User::factory()->create();
    $receptionist->assignRole(RoleEnum::Receptionist->value);

    $assistant = User::factory()->create();
    $assistant->assignRole(RoleEnum::DentalAssistant->value);

    expect(Gate::forUser($receptionist)->allows('viewAny', Appointment::class))->toBeTrue()
        ->and(Gate::forUser($receptionist)->allows('create', Appointment::class))->toBeTrue()
        ->and(Gate::forUser($this->dentist)->allows('create', Appointment::class))->toBeTrue()
        ->and(Gate::forUser($assistant)->allows('viewAny', Appointment::class))->toBeTrue()
        ->and(Gate::forUser($assistant)->allows('create', Appointment::class))->toBeFalse();
});
