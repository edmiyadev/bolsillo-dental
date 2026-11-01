<?php

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\UpdateAppointmentAction;
use App\Data\Appointments\CreateAppointmentData;
use App\Data\Appointments\UpdateAppointmentData;
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
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->clinic = Clinic::factory()->create();
    $this->branch = Branch::factory()->main()->create([
        'clinic_id' => $this->clinic->id,
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $this->dentist = User::factory()->create([
        'name' => 'Dr. Fernando Vega',
        'specialties' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);
    $this->dentist->assignRole(RoleEnum::Dentist->value);
    $this->dentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    $this->service = Service::factory()->for($this->dentist)->create([
        'name' => 'Ortodoncia Básica',
        'specialty' => DentalSpecialty::Orthodontics,
        'duration_minutes' => 45,
    ]);

    $this->cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => CubicleStatus::Active,
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);

    $this->patient = Patient::factory()->create();
});

it('creates an appointment with a service and calculates ends_at based on duration', function (): void {
    $action = app(CreateAppointmentAction::class);

    $appointment = $action->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect($appointment->is_custom_reason)->toBeFalse()
        ->and($appointment->service_id)->toBe($this->service->id)
        ->and($appointment->custom_reason)->toBeNull()
        ->and($appointment->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 10:45:00');

    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'is_custom_reason' => false,
        'service_id' => $this->service->id,
        'custom_reason' => null,
    ]);
});

it('creates an appointment with "other" service as a custom reason', function (): void {
    $action = app(CreateAppointmentAction::class);

    $appointment = $action->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => 'other',
        'custom_reason' => 'Consulta por urgencia dental',
        'starts_at' => '2026-11-02 11:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed,
    ]);

    expect($appointment->is_custom_reason)->toBeTrue()
        ->and($appointment->service_id)->toBeNull()
        ->and($appointment->custom_reason)->toBe('Consulta por urgencia dental')
        ->and($appointment->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 11:30:00');

    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'is_custom_reason' => true,
        'service_id' => null,
        'custom_reason' => 'Consulta por urgencia dental',
    ]);
});

it('updates an appointment to a custom reason and clears service_id', function (): void {
    $createAction = app(CreateAppointmentAction::class);
    $updateAction = app(UpdateAppointmentAction::class);

    $appointment = $createAction->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 09:00:00',
        'duration_minutes' => 45,
    ]);

    $updated = $updateAction->handle($appointment, [
        'service_id' => 'other',
        'custom_reason' => 'Cambio a consulta particular',
    ]);

    expect($updated->is_custom_reason)->toBeTrue()
        ->and($updated->service_id)->toBeNull()
        ->and($updated->custom_reason)->toBe('Cambio a consulta particular');

    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'is_custom_reason' => true,
        'service_id' => null,
        'custom_reason' => 'Cambio a consulta particular',
    ]);
});

it('updates an appointment from custom reason to a predefined service and clears custom_reason', function (): void {
    $createAction = app(CreateAppointmentAction::class);
    $updateAction = app(UpdateAppointmentAction::class);

    $appointment = $createAction->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => 'other',
        'custom_reason' => 'Consulta inicial',
        'starts_at' => '2026-11-02 09:00:00',
        'duration_minutes' => 30,
    ]);

    $updated = $updateAction->handle($appointment, [
        'service_id' => $this->service->id,
    ]);

    expect($updated->is_custom_reason)->toBeFalse()
        ->and($updated->service_id)->toBe($this->service->id)
        ->and($updated->custom_reason)->toBeNull();

    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'is_custom_reason' => false,
        'service_id' => $this->service->id,
        'custom_reason' => null,
    ]);
});

it('recalculates ends_at when rescheduling starts_at', function (): void {
    $createAction = app(CreateAppointmentAction::class);
    $updateAction = app(UpdateAppointmentAction::class);

    $appointment = $createAction->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 09:00:00',
        'duration_minutes' => 45,
    ]);

    $updated = $updateAction->handle($appointment, [
        'starts_at' => '2026-11-02 14:00:00',
    ]);

    expect($updated->ends_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 14:45:00');
});

it('automatically resolves and assigns an available cubicle when cubicle_id is not passed', function (): void {
    $action = app(CreateAppointmentAction::class);

    $appointment = $action->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 45,
    ]);

    expect($appointment->cubicle_id)->toBe($this->cubicle->id);
    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'cubicle_id' => $this->cubicle->id,
    ]);
});

it('throws validation exception when no cubicles are available during action execution', function (): void {
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Scheduled,
    ]);

    $action = app(CreateAppointmentAction::class);

    expect(fn () => $action->handle([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 10:15:00',
        'duration_minutes' => 30,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('always enforces Scheduled status when creating through CreateAppointmentData', function (): void {
    $data = CreateAppointmentData::fromArray([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Confirmed->value,
    ]);

    expect($data->status)->toBe(AppointmentStatus::Scheduled);

    $appointment = app(CreateAppointmentAction::class)->handle($data);

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled);
    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('allows updating status through UpdateAppointmentData', function (): void {
    $appointment = app(CreateAppointmentAction::class)->handle(
        CreateAppointmentData::fromArray([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => $this->service->id,
            'starts_at' => '2026-11-02 10:00:00',
            'duration_minutes' => 45,
        ])
    );

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled);

    $updated = app(UpdateAppointmentAction::class)->handle(
        $appointment,
        UpdateAppointmentData::fromArray([
            'status' => AppointmentStatus::Confirmed,
        ], $appointment)
    );

    expect($updated->status)->toBe(AppointmentStatus::Confirmed);
    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'status' => AppointmentStatus::Confirmed->value,
    ]);
});
