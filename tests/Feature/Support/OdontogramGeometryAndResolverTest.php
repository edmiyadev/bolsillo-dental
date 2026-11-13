<?php

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;
use App\Support\OdontogramGeometryCatalog;
use App\Support\OdontogramStateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps polygon surfaces accurately across all four quadrant orientations', function (int $tooth, array $expected): void {
    expect(OdontogramGeometryCatalog::surfaceMapping($tooth))->toBe($expected);
})->with([
    'Q1 posterior (16)' => [16, ['top' => 'V', 'bottom' => 'P', 'left' => 'D', 'right' => 'M', 'center' => 'O']],
    'Q5 anterior (51)' => [51, ['top' => 'V', 'bottom' => 'P', 'left' => 'D', 'right' => 'M', 'center' => 'I']],
    'Q2 anterior (21)' => [21, ['top' => 'V', 'bottom' => 'P', 'left' => 'M', 'right' => 'D', 'center' => 'I']],
    'Q6 posterior (65)' => [65, ['top' => 'V', 'bottom' => 'P', 'left' => 'M', 'right' => 'D', 'center' => 'O']],
    'Q4 posterior (46)' => [46, ['top' => 'L', 'bottom' => 'V', 'left' => 'D', 'right' => 'M', 'center' => 'O']],
    'Q8 anterior (82)' => [82, ['top' => 'L', 'bottom' => 'V', 'left' => 'D', 'right' => 'M', 'center' => 'I']],
    'Q3 anterior (31)' => [31, ['top' => 'L', 'bottom' => 'V', 'left' => 'M', 'right' => 'D', 'center' => 'I']],
    'Q7 posterior (74)' => [74, ['top' => 'L', 'bottom' => 'V', 'left' => 'M', 'right' => 'D', 'center' => 'O']],
]);

it('normalizes surfaces canonically and classifies tooth morphology', function (): void {
    expect(OdontogramGeometryCatalog::normalizeSurfaces(16, ['D', 'O', 'M']))->toBe(['M', 'D', 'O'])
        ->and(OdontogramGeometryCatalog::morphology(11))->toBe('incisor')
        ->and(OdontogramGeometryCatalog::morphology(13))->toBe('canine')
        ->and(OdontogramGeometryCatalog::morphology(14))->toBe('premolar')
        ->and(OdontogramGeometryCatalog::morphology(55))->toBe('molar')
        ->and(OdontogramGeometryCatalog::sameArch(15, 25))->toBeTrue()
        ->and(OdontogramGeometryCatalog::sameArch(15, 45))->toBeFalse();
});

it('resolves layered tooth state, blocked flag, bridges, and timeline views', function (): void {
    $patient = Patient::factory()->create();

    $oldCaries = OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Superseded,
    ]);

    OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['O', 'M'],
        'condition_code' => OdontogramCondition::Resin,
        'status' => OdontogramStatus::Completed,
    ]);

    OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Tooth,
        'tooth_number' => 16,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Endodontics,
        'status' => OdontogramStatus::Completed,
    ]);

    OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Tooth,
        'tooth_number' => 18,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Missing,
        'status' => OdontogramStatus::PreExisting,
    ]);

    OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Range,
        'tooth_number' => 15,
        'bridge_to_tooth' => 13,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Bridge,
        'status' => OdontogramStatus::Completed,
    ]);

    OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Mouth,
        'tooth_number' => null,
        'arch' => DentalArch::Full,
        'surfaces' => null,
        'condition_code' => OdontogramCondition::Prophylaxis,
        'status' => OdontogramStatus::Completed,
    ]);

    $entries = $patient->odontogramEntries()->orderBy('id')->get();
    $resolver = app(OdontogramStateResolver::class);

    $current = $resolver->resolve($entries, 'current');
    $tooth16 = $current['teeth'][16];
    $tooth18 = $current['teeth'][18];

    expect($tooth16['polygons']['center']['color'])->toBe('#3B82F6')
        ->and($tooth16['polygons']['right']['color'])->toBe('#3B82F6')
        ->and($tooth16['polygons']['left']['color'])->toBe('#FFFFFF')
        ->and($tooth16['hasEndodontics'])->toBeTrue()
        ->and($tooth18['isBlocked'])->toBeTrue()
        ->and($current['bridges'])->toHaveCount(1)
        ->and($current['archEntries'])->toHaveCount(1);

    $initial = $resolver->resolve($entries, 'initial');
    $initialTooth16 = $initial['teeth'][16];

    expect($initialTooth16['polygons']['center']['color'])->toBe('#EF4444')
        ->and($initialTooth16['polygons']['center']['entryId'])->toBe($oldCaries->id)
        ->and($initialTooth16['hasEndodontics'])->toBeFalse();
});

it('layers unsaved draft entries after persisted entries and resolves frozen snapshots', function (): void {
    $patient = Patient::factory()->create();

    $persisted = OdontogramEntry::factory()->for($patient)->create([
        'scope' => OdontogramScope::Surface,
        'tooth_number' => 16,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Caries,
        'status' => OdontogramStatus::Finding,
    ]);

    $draft = new OdontogramEntry([
        'patient_id' => $patient->id,
        'scope' => OdontogramScope::Surface->value,
        'tooth_number' => 16,
        'surfaces' => ['O'],
        'condition_code' => OdontogramCondition::Resin->value,
        'status' => OdontogramStatus::Completed->value,
    ]);

    $resolver = app(OdontogramStateResolver::class);
    $resolvedWithDraft = $resolver->resolve(collect([$persisted, $draft]), 'current');

    expect($resolvedWithDraft['teeth'][16]['polygons']['center']['color'])->toBe('#3B82F6')
        ->and($resolvedWithDraft['teeth'][16]['polygons']['center']['condition'])->toBe(OdontogramCondition::Resin);

    $snapshot = OdontogramSnapshot::factory()->for($patient)->create([
        'payload' => [
            'entries' => [
                [
                    'id' => 10,
                    'scope' => OdontogramScope::Surface->value,
                    'tooth_number' => 16,
                    'surfaces' => ['O'],
                    'condition_code' => OdontogramCondition::Caries->value,
                    'status' => OdontogramStatus::Finding->value,
                ],
            ],
        ],
    ]);

    $resolvedSnapshot = $resolver->resolveFromSnapshot($snapshot);

    expect($resolvedSnapshot['teeth'][16]['polygons']['center']['color'])->toBe('#EF4444')
        ->and($resolvedSnapshot['teeth'][16]['polygons']['center']['condition'])->toBe(OdontogramCondition::Caries);
});
