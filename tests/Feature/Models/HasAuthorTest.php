<?php

use App\Enums\RoleEnum;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01 08:00:00');
    seed([RoleSeeder::class, PermissionSeeder::class]);
});

it('automatically assigns created_by and updated_by using HasAuthor trait on creation and update', function (): void {
    $creator = User::factory()->create();
    $updater = User::factory()->create();

    $this->actingAs($creator);

    $patient = Patient::factory()->create([
        'first_name' => 'Carlos',
        'last_name' => 'Perez',
    ]);

    expect($patient->created_by)->toBe($creator->id)
        ->and($patient->updated_by)->toBe($creator->id)
        ->and($patient->creator->id)->toBe($creator->id)
        ->and($patient->updater->id)->toBe($creator->id);

    $this->actingAs($updater);

    $patient->update(['first_name' => 'Carlos Alberto']);

    expect($patient->refresh()->created_by)->toBe($creator->id)
        ->and($patient->updated_by)->toBe($updater->id)
        ->and($patient->creator->id)->toBe($creator->id)
        ->and($patient->updater->id)->toBe($updater->id);
});

it('allows shared models like Patient to be seen by anyone with view_patients regardless of creator', function (): void {
    $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');

    $doctor1 = User::factory()->create();
    $doctor1->assignRole($dentistRole);

    $doctor2 = User::factory()->create();
    $doctor2->assignRole($dentistRole);

    $this->actingAs($doctor1);
    $patient1 = Patient::factory()->create(['first_name' => 'Paciente Doctor 1']);

    $this->actingAs($doctor2);
    $patient2 = Patient::factory()->create(['first_name' => 'Paciente Doctor 2']);

    // Doctor 1 queries patients: can see both because Patient is shared across the clinic
    $this->actingAs($doctor1);
    $patientsForDoc1 = Patient::pluck('id')->all();
    expect($patientsForDoc1)->toContain($patient1->id, $patient2->id);
});

it('scopes appointments for user lacking view_all_appointments to their own appointments', function (): void {
    $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');

    $doctor1 = User::factory()->create();
    $doctor1->assignRole($dentistRole);

    $doctor2 = User::factory()->create();
    $doctor2->assignRole($dentistRole);

    $clinic = Clinic::factory()->create();
    $branch = Branch::factory()->create(['clinic_id' => $clinic->id]);
    $patient = Patient::factory()->create();

    $service1 = Service::factory()->create(['created_by' => $doctor1->id]);
    $service2 = Service::factory()->create(['created_by' => $doctor2->id]);

    $aptDoc1 = Appointment::factory()->create([
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'dentist_id' => $doctor1->id,
        'service_id' => $service1->id,
        'created_by' => $doctor1->id,
        'starts_at' => '2026-11-02 09:00:00',
    ]);

    $aptDoc2 = Appointment::factory()->create([
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'dentist_id' => $doctor2->id,
        'service_id' => $service2->id,
        'created_by' => $doctor2->id,
        'starts_at' => '2026-11-02 11:00:00',
    ]);

    // Doctor 1 has only view_appointments (not view_all_appointments)
    $this->actingAs($doctor1);
    $visibleAptsDoc1 = Appointment::pluck('id')->all();

    expect($visibleAptsDoc1)->toContain($aptDoc1->id)
        ->and($visibleAptsDoc1)->not->toContain($aptDoc2->id);

    // Receptionist has view_all_appointments: can see both
    $receptionistRole = Role::findOrCreate(RoleEnum::Receptionist->value, 'web');
    $receptionist = User::factory()->create();
    $receptionist->assignRole($receptionistRole);

    $this->actingAs($receptionist);
    $visibleAptsReception = Appointment::pluck('id')->all();

    expect($visibleAptsReception)->toContain($aptDoc1->id, $aptDoc2->id);
});

it('includes appointments scheduled for a dentist by reception even if created_by is reception', function (): void {
    $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');
    $doctor = User::factory()->create();
    $doctor->assignRole($dentistRole);

    $receptionistRole = Role::findOrCreate(RoleEnum::Receptionist->value, 'web');
    $receptionist = User::factory()->create();
    $receptionist->assignRole($receptionistRole);

    $clinic = Clinic::factory()->create();
    $branch = Branch::factory()->create(['clinic_id' => $clinic->id]);
    $patient = Patient::factory()->create();
    $service = Service::factory()->create(['created_by' => $doctor->id]);

    // Created by receptionist for this doctor
    $appointment = Appointment::factory()->create([
        'branch_id' => $branch->id,
        'patient_id' => $patient->id,
        'dentist_id' => $doctor->id,
        'service_id' => $service->id,
        'created_by' => $receptionist->id,
        'starts_at' => '2026-11-02 09:00:00',
    ]);

    // Doctor without view_all_appointments can see it because dentist_id === doctor->id
    $this->actingAs($doctor);
    expect(Appointment::whereKey($appointment->id)->exists())->toBeTrue();
});
