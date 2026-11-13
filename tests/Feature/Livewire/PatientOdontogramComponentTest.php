<?php

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramSnapshotType;
use App\Enums\OdontogramStatus;
use App\Enums\RoleEnum;
use App\Livewire\Patients\PatientOdontogram;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;
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

it('stages mode A clicks in memory without persisting until saveOdontogram is called and creates the initial snapshot', function (): void {
    $component = Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => true])
        ->assertOk()
        ->assertSee('18')
        ->assertSee('48')
        ->assertSee('55')
        ->assertSee(__('Save Initial Odontogram'))
        ->call('recordQuickFinding', 16, 'O', OdontogramCondition::Caries->value);

    // Nothing persisted before clicking Save Initial Odontogram
    assertDatabaseCount(OdontogramEntry::class, 0);
    assertDatabaseCount(OdontogramSnapshot::class, 0);
    expect($component->get('pendingAdditions'))->toHaveCount(1)
        ->and($component->instance()->projection['teeth'][16]['polygons']['center']['color'])->toBe('#EF4444');

    // Clicking the same surface + condition again toggles it off the draft
    $component->call('recordQuickFinding', 16, 'O', OdontogramCondition::Caries->value);
    expect($component->get('pendingAdditions'))->toHaveCount(0)
        ->and($component->instance()->projection['teeth'][16]['polygons']['center']['color'])->toBe('#FFFFFF');

    // Stage again and explicitly save
    $component->call('recordQuickFinding', 16, 'O', OdontogramCondition::Caries->value)
        ->call('saveOdontogram')
        ->assertNotified();

    assertDatabaseHas(OdontogramEntry::class, [
        'patient_id' => $this->patient->id,
        'tooth_number' => 16,
        'condition_code' => OdontogramCondition::Caries->value,
        'status' => OdontogramStatus::Finding->value,
        'service_id' => null,
    ]);

    assertDatabaseHas(OdontogramSnapshot::class, [
        'patient_id' => $this->patient->id,
        'type' => OdontogramSnapshotType::Initial->value,
        'title' => __('Initial Odontogram'),
    ]);

    expect($component->get('pendingAdditions'))->toBe([])
        ->and($component->get('hasInitialSnapshot'))->toBeTrue();
});

it('stages Mode B treatment and planned completion in draft and saves them in batch', function (): void {
    OdontogramSnapshot::factory()->for($this->patient)->create([
        'type' => OdontogramSnapshotType::Initial,
        'title' => 'Odontograma Inicial',
    ]);

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

    $component = Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => true])
        ->assertSee(__('Save Odontogram'))
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

    // Still only 1 entry in DB until saveOdontogram is called
    assertDatabaseCount(OdontogramEntry::class, 1);

    $component->call('saveOdontogram')->assertNotified();

    $planned = OdontogramEntry::query()
        ->where('patient_id', $this->patient->id)
        ->where('condition_code', OdontogramCondition::Resin->value)
        ->firstOrFail();

    expect($planned->status)->toBe(OdontogramStatus::Planned)
        ->and($planned->surfaces)->toBe(['M', 'O'])
        ->and($finding->fresh()->status)->toBe(OdontogramStatus::Finding)
        ->and($this->patient->odontogramSnapshots()->count())->toBe(1);

    // Stage completion in table and verify it waits for saveOdontogram
    $component->callAction(TestAction::make('completeEntry')->table($planned))
        ->assertNotified();

    expect($planned->fresh()->status)->toBe(OdontogramStatus::Planned);

    $component->call('saveOdontogram')->assertNotified();

    expect($planned->fresh()->status)->toBe(OdontogramStatus::Completed)
        ->and($finding->fresh()->status)->toBe(OdontogramStatus::Superseded);
});

it('allows registering a new initial odontogram snapshot and clearing the canvas in draft', function (): void {
    OdontogramSnapshot::factory()->for($this->patient)->create([
        'type' => OdontogramSnapshotType::Initial,
        'title' => 'Odontograma Inicial',
    ]);

    $oldEntry = OdontogramEntry::factory()->for($this->patient)->create([
        'tooth_number' => 11,
        'surfaces' => ['V'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $component = Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => true])
        ->call('clearCanvas')
        ->assertNotified();

    expect($component->get('pendingClearCanvas'))->toBeTrue()
        ->and($component->get('saveAsNewInitial'))->toBeTrue()
        ->and($oldEntry->fresh()->status)->toBe(OdontogramStatus::Finding);

    $component->call('recordQuickFinding', 21, 'V', OdontogramCondition::Caries->value)
        ->call('saveOdontogram')
        ->assertNotified();

    expect($oldEntry->fresh()->status)->toBe(OdontogramStatus::Superseded)
        ->and($this->patient->odontogramSnapshots()->count())->toBe(2);

    $latestSnapshot = $this->patient->odontogramSnapshots()->latest('id')->firstOrFail();

    $component->call('selectSnapshot', $latestSnapshot->id)
        ->assertSee($latestSnapshot->title);
});

it('stages mouth procedures, enforces read-only mode in infolist, and enforces update_patients authorization', function (): void {
    $component = Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => true])
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

    assertDatabaseCount(OdontogramEntry::class, 0);

    $component->call('saveOdontogram')->assertNotified();

    assertDatabaseHas(OdontogramEntry::class, [
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Mouth->value,
        'arch' => DentalArch::Full->value,
        'condition_code' => OdontogramCondition::Prophylaxis->value,
        'status' => OdontogramStatus::Completed->value,
    ]);

    // Read-only mode (ViewPatient infolist) blocks mutations even for authorized dentist
    Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => false])
        ->assertDontSee(__('Mode B: Plan / Complete Treatment'))
        ->assertDontSee(__('Save Odontogram'))
        ->call('recordQuickFinding', 12, 'V', OdontogramCondition::Caries->value)
        ->assertNotified();

    expect(OdontogramEntry::query()->where('tooth_number', 12)->count())->toBe(0);

    // Unauthorized role cannot mutate even if editable = true
    $assistant = User::factory()->create();
    $assistant->assignRole(RoleEnum::DentalAssistant->value);
    $this->actingAs($assistant);

    Livewire::test(PatientOdontogram::class, ['record' => $this->patient, 'editable' => true])
        ->call('recordQuickFinding', 11, 'V', OdontogramCondition::Caries->value)
        ->assertNotified();

    expect(OdontogramEntry::query()->where('tooth_number', 11)->count())->toBe(0);
});
