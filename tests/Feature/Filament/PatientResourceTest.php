<?php

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Enums\RoleEnum;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $user = User::factory()->create();
    $user->assignRole(RoleEnum::SuperAdmin->value);
    $this->actingAs($user);
});

it('can list and search patients', function () {
    $patients = Patient::factory()->count(3)->create();

    Livewire::test(ListPatients::class)
        ->assertCanSeeTableRecords($patients)
        ->searchTable($patients->first()->identification_number)
        ->assertCanSeeTableRecords($patients->take(1))
        ->assertCanNotSeeTableRecords($patients->skip(1));
});

it('can create a patient with demographics and medical history', function () {
    Livewire::test(CreatePatient::class)
        ->fillForm([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'document_type' => IdentificationType::IdentityCard,
            'identification_number' => '00112345678',
            'gender' => Gender::Male,
            'birth_date' => '1990-05-15',
            'email' => 'juan@example.com',
            'phone' => '8095551234',
            'residential_phone' => '8095554321',
            'work_phone' => '8095559876',
            'health_insurance' => 'Humano Seguros',
            'address' => 'Santo Domingo, DN',
            'medicalHistory' => [
                'critical_alerts' => [
                    'allergies' => ['value' => true, 'detail' => 'Penicilina y AINES'],
                    'hypertension' => ['value' => true, 'detail' => 'Controlada con Enalapril'],
                    'diabetes' => ['value' => false],
                ],
                'anamnesis_data' => [
                    'under_medical_treatment' => ['value' => true, 'detail' => 'Control cardiológico semestral'],
                    'tobacco_use' => ['value' => false],
                ],
                'general_observations' => 'Paciente presenta sensibilidad dental leve.',
            ],
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Patient::class, [
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
        'document_type' => IdentificationType::IdentityCard->value,
        'identification_number' => '00112345678',
        'gender' => Gender::Male->value,
        'email' => 'juan@example.com',
        'phone' => '8095551234',
        'residential_phone' => '8095554321',
        'work_phone' => '8095559876',
        'health_insurance' => 'Humano Seguros',
        'address' => 'Santo Domingo, DN',
    ]);

    $patient = Patient::where('identification_number', '00112345678')->firstOrFail();

    expect($patient->birth_date->format('Y-m-d'))->toBe('1990-05-15')
        ->and($patient->gender)->toBe(Gender::Male);

    assertDatabaseHas(PatientMedicalHistory::class, [
        'patient_id' => $patient->id,
        'general_observations' => 'Paciente presenta sensibilidad dental leve.',
    ]);

    expect($patient->medicalHistory->critical_alerts['allergies'])->toMatchArray(['value' => true, 'detail' => 'Penicilina y AINES'])
        ->and($patient->medicalHistory->critical_alerts['hypertension'])->toMatchArray(['value' => true, 'detail' => 'Controlada con Enalapril'])
        ->and($patient->medicalHistory->anamnesis_data['under_medical_treatment'])->toMatchArray(['value' => true, 'detail' => 'Control cardiológico semestral'])
        ->and($patient->medicalHistory->activeCriticalAlerts())->toHaveCount(2);
});

it('validates unique identification number when creating and editing a patient', function () {
    $existing = Patient::factory()->create([
        'identification_number' => '00199999999',
    ]);

    Livewire::test(CreatePatient::class)
        ->fillForm([
            'first_name' => 'María',
            'last_name' => 'Gómez',
            'document_type' => IdentificationType::IdentityCard,
            'identification_number' => '00199999999',
            'phone' => '8095550000',
        ])
        ->call('create')
        ->assertHasFormErrors(['identification_number' => 'unique']);

    // Editing the same record should ignore its own unique identification_number
    Livewire::test(EditPatient::class, ['record' => $existing->id])
        ->fillForm([
            'first_name' => 'Actualizado',
            'identification_number' => '00199999999',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Patient::class, [
        'id' => $existing->id,
        'first_name' => 'Actualizado',
    ]);
});

it('can edit a patient and update their medical history', function () {
    $patient = Patient::factory()->withMedicalHistory([
        'critical_alerts' => [
            'allergies' => ['value' => true, 'detail' => 'Penicilina'],
        ],
        'general_observations' => 'Observación inicial',
    ])->create([
        'first_name' => 'Carlos',
        'health_insurance' => 'ARS Palic',
    ]);

    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->fillForm([
            'first_name' => 'Carlos Alberto',
            'health_insurance' => 'Senasa',
            'medicalHistory' => [
                'critical_alerts' => [
                    'allergies' => ['value' => true, 'detail' => 'Penicilina y látex'],
                    'hypertension' => ['value' => true, 'detail' => 'Sospecha inicial'],
                ],
                'general_observations' => 'Observación actualizada',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(Patient::class, [
        'id' => $patient->id,
        'first_name' => 'Carlos Alberto',
        'health_insurance' => 'Senasa',
    ]);

    expect($patient->fresh()->medicalHistory->critical_alerts['allergies'])->toMatchArray(['value' => true, 'detail' => 'Penicilina y látex'])
        ->and($patient->fresh()->medicalHistory->critical_alerts['hypertension'])->toMatchArray(['value' => true, 'detail' => 'Sospecha inicial'])
        ->and($patient->fresh()->medicalHistory->general_observations)->toBe('Observación actualizada');
});

it('can view a patient including medical history', function () {
    $patient = Patient::factory()->withMedicalHistory([
        'critical_alerts' => ['Alerta crítica de prueba'],
        'general_observations' => 'Observación clínica para visualización',
    ])->create([
        'health_insurance' => 'Universal',
    ]);

    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee($patient->first_name)
        ->assertSee($patient->identification_number)
        ->assertSee('Universal')
        ->assertSee('Alerta crítica de prueba')
        ->assertSee('Observación clínica para visualización');
});

it('supports soft delete, restore, and force delete on patients', function () {
    $patient = Patient::factory()->create();

    // 1. Soft delete
    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($patient);
    expect($patient->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListPatients::class)
        ->assertCanNotSeeTableRecords([$patient])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$patient]);

    // 3. Restore
    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($patient->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $patient->delete();
    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($patient);
});
