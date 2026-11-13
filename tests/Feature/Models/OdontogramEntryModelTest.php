<?php

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
use App\Support\ModelHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('persists an odontogram entry with enums, json surfaces, relationships, and author audit', function (): void {
    $patient = Patient::factory()->create();
    $branch = Branch::factory()->create();
    $service = Service::factory()->for($this->user)->create();
    $dentist = User::factory()->create();
    $assistant = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'dentist_id' => $dentist->id,
        'service_id' => $service->id,
    ]);

    $entry = OdontogramEntry::create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'appointment_id' => $appointment->id,
        'service_id' => $service->id,
        'dentist_id' => $dentist->id,
        'assistant_id' => $assistant->id,
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['M', 'O', 'D'],
        'condition_code' => OdontogramCondition::Resin,
        'status' => OdontogramStatus::Completed,
        'notes' => 'Resina compuesta 3 caras',
    ]);

    expect($entry->scope)->toBe(OdontogramScope::Surface)
        ->and($entry->condition_code)->toBe(OdontogramCondition::Resin)
        ->and($entry->status)->toBe(OdontogramStatus::Completed)
        ->and($entry->surfaces)->toBe(['M', 'O', 'D'])
        ->and($entry->created_by)->toBe($this->user->id)
        ->and($entry->updated_by)->toBe($this->user->id)
        ->and($entry->patient->id)->toBe($patient->id)
        ->and($entry->branch->id)->toBe($branch->id)
        ->and($entry->appointment->id)->toBe($appointment->id)
        ->and($entry->service->id)->toBe($service->id)
        ->and($entry->dentist->id)->toBe($dentist->id)
        ->and($entry->assistant->id)->toBe($assistant->id)
        ->and($patient->odontogramEntries()->count())->toBe(1);
});

it('filters entries accurately for current and initial timeline scopes', function (): void {
    $patient = Patient::factory()->create();

    $finding = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Finding]);
    $preExisting = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::PreExisting]);
    $planned = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Planned]);
    $completed = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Completed]);
    $superseded = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Superseded]);
    OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Cancelled]);

    $currentIds = OdontogramEntry::query()->forPatient($patient->id)->activeInCurrentView()->pluck('id')->all();
    $initialIds = OdontogramEntry::query()->forPatient($patient->id)->forInitialView()->pluck('id')->all();

    expect($currentIds)->toEqualCanonicalizing([$finding->id, $preExisting->id, $planned->id, $completed->id])
        ->and($initialIds)->toEqualCanonicalizing([$finding->id, $preExisting->id, $superseded->id]);
});

it('soft deletes odontogram entries and excludes OdontogramEntry from ModelHelper permissions list', function (): void {
    $entry = OdontogramEntry::factory()->create([
        'scope' => OdontogramScope::Mouth,
        'tooth_number' => null,
        'arch' => DentalArch::Full,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Prophylaxis,
    ]);

    $entry->delete();
    assertSoftDeleted($entry);

    expect(ModelHelper::getModelsWithExclusions())
        ->not->toContain('OdontogramEntry')
        ->not->toContain('OdontogramSnapshot');
});

it('tracks odontogram presence on patient and persists immutable snapshots', function (): void {
    $patient = Patient::factory()->create();

    expect($patient->hasOdontogram())->toBeFalse();

    $snapshot = OdontogramSnapshot::factory()->for($patient)->create([
        'type' => OdontogramSnapshotType::Initial,
        'title' => 'Odontograma Inicial',
        'payload' => [
            'entries' => [
                [
                    'id' => 1,
                    'scope' => OdontogramScope::Surface->value,
                    'tooth_number' => 16,
                    'surfaces' => ['O'],
                    'condition_code' => OdontogramCondition::Caries->value,
                    'status' => OdontogramStatus::Finding->value,
                ],
            ],
        ],
    ]);

    expect($patient->fresh()->hasOdontogram())->toBeTrue()
        ->and($snapshot->type)->toBe(OdontogramSnapshotType::Initial)
        ->and($snapshot->created_by)->toBe($this->user->id)
        ->and($snapshot->patient->id)->toBe($patient->id)
        ->and($patient->odontogramSnapshots()->count())->toBe(1);
});
