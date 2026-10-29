<?php

use App\Enums\IdentificationType;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list and search patients', function () {
    $patients = Patient::factory()->count(3)->create();

    Livewire::test(ListPatients::class)
        ->assertCanSeeTableRecords($patients)
        ->searchTable($patients->first()->identification_number)
        ->assertCanSeeTableRecords($patients->take(1))
        ->assertCanNotSeeTableRecords($patients->skip(1));
});

it('can create a patient', function () {
    Livewire::test(CreatePatient::class)
        ->fillForm([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'document_type' => IdentificationType::IdentityCard,
            'identification_number' => '00112345678',
            'email' => 'juan@example.com',
            'phone' => '8095551234',
            'address' => 'Santo Domingo, DN',
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
        'email' => 'juan@example.com',
        'phone' => '8095551234',
        'address' => 'Santo Domingo, DN',
    ]);
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

it('can view a patient', function () {
    $patient = Patient::factory()->create();

    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee($patient->first_name)
        ->assertSee($patient->identification_number);
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
