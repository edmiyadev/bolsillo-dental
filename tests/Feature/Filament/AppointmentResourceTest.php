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

it('can create an appointment with a predefined service and auto-fills duration', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => $this->service->id,
            'starts_at' => '2026-11-02 10:00:00',
            'observations' => 'Paciente con brackets autoligados',
        ])
        ->assertFormSet([
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
            'service_id' => 'other',
            'custom_reason' => 'Dolor agudo en molar inferior derecho',
            'duration_minutes' => 30,
            'starts_at' => '2026-11-02 11:00:00',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'is_custom_reason' => true,
        'service_id' => null,
        'custom_reason' => 'Dolor agudo en molar inferior derecho',
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('sets is_custom_reason to true and service_id to null when selecting "other" service option', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => 'other',
            'custom_reason' => 'Extracción de emergencia',
            'duration_minutes' => 30,
            'starts_at' => '2026-11-02 12:00:00',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'is_custom_reason' => true,
        'service_id' => null,
        'custom_reason' => 'Extracción de emergencia',
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('prevents creating an overlapping appointment on the form', function (): void {
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'starts_at' => '2026-11-02 10:00:00',
        'duration_minutes' => 60,
        'status' => AppointmentStatus::Scheduled,
    ]);

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'dentist_id' => $this->dentist->id,
            'service_id' => 'other',
            'custom_reason' => 'Consulta de urgencia',
            'duration_minutes' => 30,
            'starts_at' => '2026-11-02 10:15:00',
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

it('clears dentist_id if selected dentist does not support the newly chosen service specialty', function (): void {
    $generalDentist = User::factory()->create([
        'name' => 'Dr. General Solo',
        'specialties' => [DentalSpecialty::General->value],
    ]);
    $generalDentist->assignRole(RoleEnum::Dentist->value);
    $generalDentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'dentist_id' => $generalDentist->id,
        ])
        ->set('data.service_id', $this->service->id)
        ->assertFormSet([
            'dentist_id' => null,
        ]);
});

it('rejects saving appointment if dentist does not support the required service specialty', function (): void {
    $generalDentist = User::factory()->create([
        'name' => 'Dr. General Incompatible',
        'specialties' => [DentalSpecialty::General->value],
    ]);
    $generalDentist->assignRole(RoleEnum::Dentist->value);
    $generalDentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'dentist_id' => $generalDentist->id,
            'starts_at' => '2026-11-02 14:00:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['dentist_id']);
});

it('allows saving appointment when dentist supports the required service specialty', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'dentist_id' => $this->dentist->id,
            'starts_at' => '2026-11-02 15:00:00',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('automatically assigns an available cubicle when creating an appointment without cubicle_id', function (): void {
    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'dentist_id' => $this->dentist->id,
            'starts_at' => '2026-11-02 16:00:00',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Appointment::class, [
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
        'service_id' => $this->service->id,
        'cubicle_id' => $this->cubicle->id,
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

it('fails validation on starts_at when no cubicle is available for the specialty in the requested time slot', function (): void {
    // Occupy the only cubicle in this branch for that time slot
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-02 17:00:00',
        'duration_minutes' => 45,
        'status' => AppointmentStatus::Scheduled,
    ]);

    // Create another dentist for the same branch who is available
    $secondDentist = User::factory()->create([
        'name' => 'Dra. Segunda Especialista',
        'specialties' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);
    $secondDentist->assignRole(RoleEnum::Dentist->value);
    $secondDentist->branchSchedules()->create([
        'branch_id' => $this->branch->id,
        'schedule' => DayOfWeek::defaultSchedule(),
        'is_active' => true,
    ]);

    Livewire::test(CreateAppointment::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'service_id' => $this->service->id,
            'dentist_id' => $secondDentist->id,
            'starts_at' => '2026-11-02 17:15:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_at']);
});

it('hides status field on creation and shows it on edit', function (): void {
    Livewire::test(CreateAppointment::class)
        ->assertFormFieldIsHidden('status');

    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'service_id' => $this->service->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->assertFormFieldIsVisible('status');
});
