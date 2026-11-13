<?php

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Enums\RoleEnum;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\PatientResource;
use App\Http\Middleware\InitializeTenancyByDomainOrSubdomain;
use App\Livewire\Patients\PatientOdontogram;
use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use App\Models\User;
use App\Support\MedicalHistoryCatalog;
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
                'medical_data' => [
                    'allergies' => ['value' => true, 'detail' => 'Penicilina y AINES', 'is_critical' => true, 'has_detail' => true],
                    'hypertension' => ['value' => true, 'detail' => 'Controlada con Enalapril', 'is_critical' => true, 'has_detail' => true],
                    'diabetes' => ['value' => false, 'is_critical' => true, 'has_detail' => true],
                    'under_medical_treatment' => ['value' => true, 'detail' => 'Control cardiológico semestral', 'is_critical' => false, 'has_detail' => true],
                    'tobacco_use' => ['value' => false, 'is_critical' => false, 'has_detail' => true],
                ],
                'general_observations' => 'Paciente presenta sensibilidad dental leve.',
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
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

    expect($patient->medicalHistory->medical_data['allergies'])->toMatchArray(['value' => true, 'detail' => 'Penicilina y AINES'])
        ->and($patient->medicalHistory->medical_data['hypertension'])->toMatchArray(['value' => true, 'detail' => 'Controlada con Enalapril'])
        ->and($patient->medicalHistory->medical_data['under_medical_treatment'])->toMatchArray(['value' => true, 'detail' => 'Control cardiológico semestral'])
        ->and(MedicalHistoryCatalog::activeCriticalAlerts($patient->medicalHistory->medical_data))->toHaveCount(2);
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
        'medical_data' => [
            'allergies' => ['value' => true, 'detail' => 'Penicilina', 'is_critical' => true, 'has_detail' => true],
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
                'medical_data' => [
                    'allergies' => ['value' => true, 'detail' => 'Penicilina y látex', 'is_critical' => true, 'has_detail' => true],
                    'hypertension' => ['value' => true, 'detail' => 'Sospecha inicial', 'is_critical' => true, 'has_detail' => true],
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

    expect($patient->fresh()->medicalHistory->medical_data['allergies'])->toMatchArray(['value' => true, 'detail' => 'Penicilina y látex'])
        ->and($patient->fresh()->medicalHistory->medical_data['hypertension'])->toMatchArray(['value' => true, 'detail' => 'Sospecha inicial'])
        ->and($patient->fresh()->medicalHistory->general_observations)->toBe('Observación actualizada');
});

it('can view a patient including medical history', function () {
    $patient = Patient::factory()->withMedicalHistory([
        'medical_data' => [
            'allergies' => ['value' => true, 'detail' => 'Penicilina', 'is_critical' => true, 'has_detail' => true],
            'under_medical_treatment' => ['value' => true, 'detail' => 'Tratamiento cardiológico', 'is_critical' => false, 'has_detail' => true],
        ],
        'general_observations' => 'Observación clínica para visualización',
    ])->create([
        'health_insurance' => 'Universal',
    ]);

    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee($patient->first_name)
        ->assertSee($patient->last_name)
        ->assertSee($patient->identification_number)
        ->assertSee('Universal')
        ->assertSee('Alergias: Penicilina')
        ->assertSee('Tratamiento médico actual: Tratamiento cardiológico')
        ->assertSee('SÍ (Penicilina)')
        ->assertSee('NO')
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

it('renders read-only odontogram on view patient, editable odontogram on edit patient, and hides odontogram on create patient', function () {
    $patient = Patient::factory()->create();

    $dentist = User::factory()->create();
    $dentist->assignRole(RoleEnum::Dentist->value);
    $this->actingAs($dentist);

    // ViewPatient is read-only even for dentist
    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee(__('Odontogram'))
        ->assertSeeLivewire(PatientOdontogram::class)
        ->assertDontSee(__('Mode B: Plan / Complete Treatment'))
        ->assertDontSee(__('Save Initial Odontogram'));

    // EditPatient renders the editable odontogram
    Livewire::test(EditPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee(__('Odontogram'))
        ->assertSeeLivewire(PatientOdontogram::class);

    // CreatePatient hides the Odontogram tab
    Livewire::test(CreatePatient::class)
        ->assertOk()
        ->assertDontSeeLivewire(PatientOdontogram::class);

    $this->withoutMiddleware(InitializeTenancyByDomainOrSubdomain::class)
        ->get(PatientResource::getUrl('view', ['record' => $patient]))
        ->assertOk()
        ->assertSee('odontogram-', false);

    $assistant = User::factory()->create();
    $assistant->assignRole(RoleEnum::DentalAssistant->value);
    $this->actingAs($assistant);

    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee(__('Odontogram'))
        ->assertSeeLivewire(PatientOdontogram::class)
        ->assertDontSee(__('Mode B: Plan / Complete Treatment'));
});
