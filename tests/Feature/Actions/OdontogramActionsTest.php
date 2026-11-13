<?php

use App\Actions\Odontogram\CancelOdontogramEntryAction;
use App\Actions\Odontogram\CompleteOdontogramEntryAction;
use App\Actions\Odontogram\CreateOdontogramSnapshotAction;
use App\Actions\Odontogram\RecordOdontogramEntryAction;
use App\Actions\Odontogram\SaveOdontogramSessionAction;
use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramSnapshotType;
use App\Enums\OdontogramStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->patient = Patient::factory()->create();
    $this->service = Service::factory()->for($this->user)->create();
    $this->branch = Branch::factory()->create();
});

it('records a multi-surface treatment as a single entry and supersedes overlapping findings only', function (): void {
    $cariesO = OdontogramEntry::factory()->for($this->patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $cariesD = OdontogramEntry::factory()->for($this->patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['D'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $dentist = User::factory()->create();
    $assistant = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'dentist_id' => $dentist->id,
    ]);

    $treatment = app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Surface->value,
        'tooth_number' => 16,
        'surfaces' => ['O', 'M'],
        'condition_code' => OdontogramCondition::Resin->value,
        'status' => OdontogramStatus::Completed->value,
        'service_id' => $this->service->id,
        'appointment_id' => $appointment->id,
        'branch_id' => $this->branch->id,
        'dentist_id' => $dentist->id,
        'assistant_id' => $assistant->id,
    ]);

    expect($treatment->surfaces)->toBe(['M', 'O'])
        ->and($cariesO->fresh()->status)->toBe(OdontogramStatus::Superseded)
        ->and($cariesD->fresh()->status)->toBe(OdontogramStatus::Finding);
});

it('completes a planned crown and supersedes all surface findings on that tooth', function (): void {
    $findingM = OdontogramEntry::factory()->for($this->patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 24,
        'surfaces' => ['M', 'O'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $plannedCrown = app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Tooth->value,
        'tooth_number' => 24,
        'condition_code' => OdontogramCondition::Crown->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
        'dentist_id' => $this->user->id,
    ]);

    expect($findingM->fresh()->status)->toBe(OdontogramStatus::Finding);

    $completedCrown = app(CompleteOdontogramEntryAction::class)->handle($plannedCrown, [
        'notes' => 'Corona instalada',
    ]);

    expect($completedCrown->status)->toBe(OdontogramStatus::Completed)
        ->and($completedCrown->notes)->toBe('Corona instalada')
        ->and($findingM->fresh()->status)->toBe(OdontogramStatus::Superseded);
});

it('blocks surface or crown treatments on missing or extracted teeth while allowing implant and bridge', function (): void {
    OdontogramEntry::factory()->for($this->patient)->create([
        'scope' => OdontogramScope::Tooth,
        'tooth_number' => 26,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Extraction,
        'status' => OdontogramStatus::Completed,
    ]);

    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Surface->value,
        'tooth_number' => 26,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Resin->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
    ]))->toThrow(ValidationException::class);

    $implant = app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Tooth->value,
        'tooth_number' => 26,
        'condition_code' => OdontogramCondition::Implant->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
    ]);

    expect($implant->condition_code)->toBe(OdontogramCondition::Implant);
});

it('validates sealant, bridge same-arch rules, required service on treatments, and cancellation', function (): void {
    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Surface->value,
        'tooth_number' => 11,
        'surfaces' => ['I'],
        'condition_code' => OdontogramCondition::Sealant->value,
        'status' => OdontogramStatus::Completed->value,
        'service_id' => $this->service->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Range->value,
        'tooth_number' => 14,
        'bridge_to_tooth' => 44,
        'condition_code' => OdontogramCondition::Bridge->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Mouth->value,
        'arch' => DentalArch::Full->value,
        'condition_code' => OdontogramCondition::Prophylaxis->value,
        'status' => OdontogramStatus::Planned->value,
    ]))->toThrow(ValidationException::class);

    $mouthEntry = app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Mouth->value,
        'arch' => DentalArch::Full->value,
        'condition_code' => OdontogramCondition::Prophylaxis->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
    ]);

    $cancelled = app(CancelOdontogramEntryAction::class)->handle($mouthEntry);
    expect($cancelled->status)->toBe(OdontogramStatus::Cancelled);
});

it('saves staged session changes in batch, creates initial snapshots, and supersedes prior active entries on canvas reset', function (): void {
    $sessionAction = app(SaveOdontogramSessionAction::class);

    $firstResult = $sessionAction->handle(
        patient: $this->patient,
        pendingAdditions: [
            [
                'scope' => OdontogramScope::Surface->value,
                'tooth_number' => 16,
                'surfaces' => ['O'],
                'condition_code' => OdontogramCondition::Caries->value,
                'status' => OdontogramStatus::Finding->value,
                'dentist_id' => $this->user->id,
            ],
        ],
    );

    expect($firstResult['snapshot'])->toBeInstanceOf(OdontogramSnapshot::class)
        ->and($firstResult['snapshot']->type)->toBe(OdontogramSnapshotType::Initial)
        ->and($firstResult['snapshot']->payload['entries'])->toHaveCount(1);

    $evolutionResult = $sessionAction->handle(
        patient: $this->patient,
        pendingAdditions: [
            [
                'scope' => OdontogramScope::Surface->value,
                'tooth_number' => 16,
                'surfaces' => ['O'],
                'condition_code' => OdontogramCondition::Resin->value,
                'status' => OdontogramStatus::Completed->value,
                'service_id' => $this->service->id,
                'dentist_id' => $this->user->id,
            ],
        ],
        asInitialSnapshot: false,
    );

    expect($evolutionResult['snapshot'])->toBeNull()
        ->and($this->patient->odontogramSnapshots()->count())->toBe(1);

    $resetResult = $sessionAction->handle(
        patient: $this->patient,
        pendingAdditions: [
            [
                'scope' => OdontogramScope::Tooth->value,
                'tooth_number' => 21,
                'condition_code' => OdontogramCondition::Fracture->value,
                'status' => OdontogramStatus::Finding->value,
                'dentist_id' => $this->user->id,
            ],
        ],
        clearCanvas: true,
        asInitialSnapshot: true,
    );

    expect($resetResult['snapshot'])->toBeInstanceOf(OdontogramSnapshot::class)
        ->and($resetResult['snapshot']->title)->toBe(__('Initial Odontogram #:number', ['number' => 2]))
        ->and($resetResult['snapshot']->payload['entries'])->toHaveCount(1)
        ->and($firstResult['snapshot']->fresh()->payload['entries'])->toHaveCount(1)
        ->and(OdontogramEntry::query()->forPatient($this->patient->id)->activeInCurrentView()->count())->toBe(1);
});

it('captures a standalone snapshot of active entries only', function (): void {
    OdontogramEntry::factory()->for($this->patient)->create([
        'tooth_number' => 11,
        'surfaces' => ['V'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    OdontogramEntry::factory()->for($this->patient)->create([
        'tooth_number' => 12,
        'surfaces' => ['M'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Superseded,
    ]);

    $snapshot = app(CreateOdontogramSnapshotAction::class)->handle($this->patient);

    expect($snapshot->payload['entries'])->toHaveCount(1)
        ->and($snapshot->payload['entries'][0]['tooth_number'])->toBe(11);
});
