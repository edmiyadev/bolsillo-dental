<?php

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Enums\RoleEnum;
use App\Livewire\Patients\PatientOdontogram;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->dentist = User::factory()->create();
    $this->dentist->assignRole(RoleEnum::Dentist->value);
    $this->actingAs($this->dentist);

    $this->patient = Patient::factory()->create();
    $this->branch = Branch::factory()->create();
    $this->service = Service::factory()->for($this->dentist)->create(['name' => 'Resina Compuesta']);
});

it('renders permanent and primary tabs and records mode A 1-click findings', function (): void {
    Livewire::test(PatientOdontogram::class, ['record' => $this->patient])
        ->assertOk()
        ->assertSee('18')
        ->assertSee('48')
        ->assertSee('55')
        ->call('recordQuickFinding', 16, 'O', OdontogramCondition::Caries->value)
        ->assertNotified();

    assertDatabaseHas(OdontogramEntry::class, [
        'patient_id' => $this->patient->id,
        'tooth_number' => 16,
        'condition_code' => OdontogramCondition::Caries->value,
        'status' => OdontogramStatus::Finding->value,
        'service_id' => null,
    ]);
});

it('registers a multi-surface treatment via Mode B modal and marks planned record completed in table', function (): void {
    $finding = OdontogramEntry::factory()->for($this->patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $assistant = User::factory()->create();
    $assistant->assignRole(RoleEnum::DentalAssistant->value);

    $appointment = Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'dentist_id' => $this->dentist->id,
    ]);

    $component = Livewire::test(PatientOdontogram::class, ['record' => $this->patient])
        ->callAction(
            TestAction::make('registerTreatment')->arguments([
                'scope' => OdontogramScope::Surface->value,
                'tooth_number' => 16,
                'surfaces' => ['M', 'O'],
            ]),
            data: [
                'condition_code' => OdontogramCondition::Resin->value,
                'service_id' => $this->service->id,
                'status' => OdontogramStatus::Planned->value,
                'appointment_id' => $appointment->id,
                'branch_id' => $this->branch->id,
                'dentist_id' => $this->dentist->id,
                'assistant_id' => $assistant->id,
                'notes' => 'Planificado en consulta',
            ],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    $planned = OdontogramEntry::query()
        ->where('patient_id', $this->patient->id)
        ->where('condition_code', OdontogramCondition::Resin->value)
        ->firstOrFail();

    expect($planned->status)->toBe(OdontogramStatus::Planned)
        ->and($planned->surfaces)->toBe(['M', 'O'])
        ->and($finding->fresh()->status)->toBe(OdontogramStatus::Finding);

    $component->callAction(TestAction::make('completeEntry')->table($planned))
        ->assertNotified();

    expect($planned->fresh()->status)->toBe(OdontogramStatus::Completed)
        ->and($finding->fresh()->status)->toBe(OdontogramStatus::Superseded);
});

it('registers an arch or mouth procedure via header action and enforces update_patients authorization', function (): void {
    Livewire::test(PatientOdontogram::class, ['record' => $this->patient])
        ->callAction(
            TestAction::make('registerMouthProcedure'),
            data: [
                'scope' => OdontogramScope::Mouth->value,
                'arch' => DentalArch::Full->value,
                'condition_code' => OdontogramCondition::Prophylaxis->value,
                'service_id' => $this->service->id,
                'status' => OdontogramStatus::Completed->value,
                'dentist_id' => $this->dentist->id,
            ],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    assertDatabaseHas(OdontogramEntry::class, [
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Mouth->value,
        'arch' => DentalArch::Full->value,
        'condition_code' => OdontogramCondition::Prophylaxis->value,
        'status' => OdontogramStatus::Completed->value,
    ]);

    $assistant = User::factory()->create();
    $assistant->assignRole(RoleEnum::DentalAssistant->value);
    $this->actingAs($assistant);

    Livewire::test(PatientOdontogram::class, ['record' => $this->patient])
        ->call('recordQuickFinding', 11, 'V', OdontogramCondition::Caries->value);

    expect(OdontogramEntry::query()->where('tooth_number', 11)->count())->toBe(0);
});
