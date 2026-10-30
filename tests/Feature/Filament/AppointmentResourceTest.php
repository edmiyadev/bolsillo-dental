<?php

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
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
use Livewire\Livewire;

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
        'name' => 'Dr. Carlos Ramírez',
        'specialties' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);
    $this->dentist->assignRole(RoleEnum::Dentist->value);
    $this->dentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    $this->service = Service::factory()->for($this->dentist)->create([
        'name' => 'Control de Ortodoncia',
        'specialty' => DentalSpecialty::Orthodontics,
        'duration_minutes' => 45,
    ]);

    $this->cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => CubicleStatus::Active,
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);

    $this->patient = Patient::factory()->create();

    $receptionist = User::factory()->create();
    $receptionist->assignRole(RoleEnum::Receptionist->value);
    $this->actingAs($receptionist);
});

it('can create an appointment with a predefined service and auto-fills duration and specialty', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'is_custom_reason' => false,
            'service_id' => $this->service->id,
            'cubicle_id' => $this->cubicle->id,
            'starts_at' => '2026-11-02 10:00:00',
            'status' => AppointmentStatus::Scheduled->value,
            'observations' => 'Paciente con brackets autoligados',
        ])
        ->assertFormSet([
            'specialty' => DentalSpecialty::Orthodontics->value,
            'duration_minutes' => 45,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Appointment::class, [
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'specialty' => DentalSpecialty::Orthodontics->value,
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('can create an appointment with custom reason ("Otros") without a predefined service', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'is_custom_reason' => true,
            'custom_reason' => 'Dolor agudo en molar inferior derecho',
            'specialty' => DentalSpecialty::General->value,
            'duration_minutes' => 30,
            'cubicle_id' => $this->cubicle->id,
            'starts_at' => '2026-11-02 11:00:00',
            'status' => AppointmentStatus::Confirmed->value,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'is_custom_reason' => true,
        'service_id' => null,
        'custom_reason' => 'Dolor agudo en molar inferior derecho',
    ]);
});

it('prevents creating an overlapping appointment on the form', function (): void {
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 60,
        'status' => AppointmentStatus::Scheduled,
    ]);

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'is_custom_reason' => true,
            'custom_reason' => 'Consulta de urgencia',
            'specialty' => DentalSpecialty::General->value,
            'duration_minutes' => 30,
            'cubicle_id' => $this->cubicle->id,
            'starts_at' => '2026-11-02 10:15:00',
            'status' => AppointmentStatus::Scheduled->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_at']);
});

it('can edit an existing appointment without self-overlap validation errors', function (): void {
    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'is_custom_reason' => false,
        'specialty' => DentalSpecialty::Orthodontics,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Scheduled,
        'observations' => 'Observación inicial',
    ]);

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->fillForm([
            'status' => AppointmentStatus::Confirmed->value,
            'observations' => 'Paciente confirmó asistencia por teléfono',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'id' => $appointment->id,
        'status' => AppointmentStatus::Confirmed->value,
        'observations' => 'Paciente confirmó asistencia por teléfono',
    ]);
});
