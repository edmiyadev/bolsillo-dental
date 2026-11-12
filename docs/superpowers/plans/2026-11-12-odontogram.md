# Odontogram Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an interactive, evolutionary FDI dental odontogram embedded in the Patient view page, supporting Permanent and Primary dentition tabs, anatomical tooth SVGs with 5-face surface boxes, Mode A (1-click findings/pre-existing) and Mode B (planned/completed treatments linked to Service, Dentist, Assistant, Appointment, and Branch), and full historical state tracking without deleting initial diagnoses.

**Architecture:** A tenant-scoped `OdontogramEntry` model backed by dedicated domain Enums, an FDI geometry/projection support layer (`OdontogramGeometryCatalog` + `OdontogramStateResolver`), and transactional Actions (`RecordOdontogramEntryAction`, `CompleteOdontogramEntryAction`, `CancelOdontogramEntryAction`). The UI is delivered via a Filament 5 `Livewire` schema component (`PatientOdontogram`) implementing `HasActions`, `HasSchemas`, and `HasTable`, using Alpine.js for instant multi-surface/bridge selection and tab switching, and Blade SVG components for anatomical teeth and 5-face boxes.

**Tech Stack:** PHP 8.4, Laravel 13, Filament 5 (`filament/filament ^5.0`), Livewire, Alpine.js, Tailwind CSS v4, Pest 5.

**Spec:** [odontograma-design.md](file:///home/edward/.gemini/antigravity-cli/brain/ef0ffc12-2198-498a-a860-f52f8ce5d9f7/odontograma-design.md) and domain reference [odontograma.md](file:///home/edward/code/personal-projects/bolsillo-dental/odontograma.md) + layout image [image.png](file:///home/edward/code/personal-projects/bolsillo-dental/image.png).

## Global Constraints

- Tenant database migration goes in `database/migrations/tenant/`.
- `OdontogramEntry` uses `HasAuthor` (`created_by`, `updated_by`) and `SoftDeletes`, plus `#[Fillable([...])]` and `casts(): array`. Do NOT apply `BelongsToUser` to `OdontogramEntry` (clinical records belong to the patient and are shared across authorized clinic staff).
- Exclude `OdontogramEntry` in `ModelHelper::getModelsWithExclusions()` so access relies on `view_patients` and `update_patients`.
- No budget, cash register, or `CommissionRecord` integration in this phase.
- Never physically `DELETE` initial `finding` records when treated; transition overlapping `finding` entries to `superseded`.
- Multi-surface restorations (e.g., `M-O-D`) must persist as a single `OdontogramEntry` row with canonical sorted `surfaces` JSON array.
- All PHP control structures require curly braces, explicit parameter/return types, constructor property promotion, PHPDoc array shapes, and formatting with `vendor/bin/pint --dirty --format agent`.
- All UI strings must be wrapped in `__()` with Spanish translations in `lang/es.json`.

## Review Focus

- **Quadrant Surface Inversion:** `left`/`right` (Mesial/Distal) and `top`/`bottom` (Vestibular/Palatine-Lingual) must invert cleanly across all 8 FDI quadrants, with `center` resolving to `I` on teeth `1..3` and `O` on teeth `4..8`.
- **Blocked Tooth Enforcement:** Teeth with `missing` or `completed` `extraction` must block new `surface` records, `crown`, `endodontics`, `fracture`, and `extraction`, while still allowing `implant` and `bridge` endpoints.
- **Bridge Same-Arch Validation:** `range` entries must reject mismatched arches (e.g., upper `14` to lower `44`) and identical start/end teeth (`14` to `14`).
- **Partial Surface Overlap on Supersede:** Completing a `resin` on `["O", "M"]` must supersede an existing `finding` on `["O"]` or `["O", "M"]`, without superseding an unrelated `finding` on `["D"]` of the same tooth.
- **Authorization Boundaries:** Users with `view_patients` but without `update_patients` (e.g., `DentalAssistant`) can view the odontogram and table, but cannot trigger Mode A clicks, Mode B treatments, complete, cancel, or delete entries.

---

### Task 1: Domain Enums, Tenant Migration, `OdontogramEntry` Model, Factory, and `ModelHelper`

**Files:**
- Create: `app/Enums/DentalArch.php`
- Create: `app/Enums/OdontogramScope.php`
- Create: `app/Enums/OdontogramStatus.php`
- Create: `app/Enums/OdontogramCondition.php`
- Create: `database/migrations/tenant/2026_11_12_000002_create_odontogram_entries_table.php`
- Create: `app/Models/OdontogramEntry.php`
- Create: `database/factories/OdontogramEntryFactory.php`
- Modify: `app/Models/Patient.php:1-55`
- Modify: `app/Support/ModelHelper.php:20-30`
- Test: `tests/Feature/Models/OdontogramEntryModelTest.php`

**Interfaces:**
- Produces:
  - `App\Enums\DentalArch`: cases `Upper = 'upper'`, `Lower = 'lower'`, `Full = 'full'`; `getLabel(): string`
  - `App\Enums\OdontogramScope`: cases `Surface = 'surface'`, `Tooth = 'tooth'`, `Range = 'range'`, `Arch = 'arch'`, `Mouth = 'mouth'`; `getLabel(): string`
  - `App\Enums\OdontogramStatus`: cases `Finding = 'finding'`, `PreExisting = 'pre_existing'`, `Planned = 'planned'`, `Completed = 'completed'`, `Superseded = 'superseded'`, `Cancelled = 'cancelled'`; `getLabel(): string`, `getColor(): string`, `hexColor(): string`, `isActiveInCurrentView(): bool`, `isVisibleInInitialView(): bool`
  - `App\Enums\OdontogramCondition`: cases `Caries`, `Resin`, `Amalgam`, `Inlay`, `Sealant`, `Fracture`, `Extraction`, `Missing`, `Endodontics`, `Crown`, `Implant`, `Bridge`, `Prophylaxis`; `getLabel(): string`, `defaultScope(): OdontogramScope`, `isFindingCondition(): bool`, `isPreExistingCondition(): bool`, `isTreatmentCondition(): bool`, `supportsScope(OdontogramScope $scope): bool`
  - `App\Models\OdontogramEntry`: relations `patient()`, `branch()`, `appointment()`, `service()`, `dentist()`, `assistant()`; scopes `scopeForPatient(Builder $query, int $patientId)`, `scopeActiveInCurrentView(Builder $query)`, `scopeForInitialView(Builder $query)`
  - `App\Models\Patient::odontogramEntries(): HasMany`

- [ ] **Step 1: Write the failing model and enum test**

Create `tests/Feature/Models/OdontogramEntryModelTest.php`:

```php
<?php

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
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
    $cancelled = OdontogramEntry::factory()->for($patient)->create(['status' => OdontogramStatus::Cancelled]);

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

    expect(ModelHelper::getModelsWithExclusions())->not->toContain('OdontogramEntry');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Models/OdontogramEntryModelTest.php`
Expected: FAIL (`Class "App\Enums\OdontogramScope" not found`).

- [ ] **Step 3: Create the 4 domain Enums in `app/Enums/`**

Create `DentalArch.php`, `OdontogramScope.php`, `OdontogramStatus.php`, and `OdontogramCondition.php` using `HasLabel` (and `HasColor` on `OdontogramStatus` and `OdontogramCondition`).
On `OdontogramCondition`, include `defaultScope(): OdontogramScope`, `supportsScope(OdontogramScope $scope): bool`, `isFindingCondition(): bool` (`Caries`, `Fracture`), `isPreExistingCondition(): bool` (`Resin`, `Amalgam`, `Inlay`, `Sealant`, `Missing`, `Endodontics`, `Crown`, `Implant`, `Bridge`), and `isTreatmentCondition(): bool` (`Resin`, `Amalgam`, `Inlay`, `Sealant`, `Fracture`? No — treatments: `Resin`, `Amalgam`, `Inlay`, `Sealant`, `Extraction`, `Endodontics`, `Crown`, `Implant`, `Bridge`, `Prophylaxis`). Also add `hexColor(): string` on `OdontogramStatus` (`Finding`/`Planned` -> `#EF4444`, `Completed` -> `#3B82F6`, `PreExisting` -> `#10B981`, `Superseded`/`Cancelled` -> `#64748B`).

- [ ] **Step 4: Create the tenant migration `database/migrations/tenant/2026_11_12_000002_create_odontogram_entries_table.php`**

Define columns matching the spec table (`patient_id` cascadeOnDelete, `branch_id`, `appointment_id`, `service_id`, `dentist_id`, `assistant_id` all nullable with `nullOnDelete()`, `scope` string(20), `tooth_number` unsignedTinyInteger nullable, `bridge_to_tooth` unsignedTinyInteger nullable, `arch` string(15) nullable, `surfaces` json nullable, `condition_code` string(40), `status` string(30), `notes` text nullable, `created_by` & `updated_by` unsignedBigInteger nullable indexed, `timestamps()`, `softDeletes()`). Add composite index `['patient_id', 'status']` and `['patient_id', 'tooth_number']`.

- [ ] **Step 5: Create `OdontogramEntry` model, `OdontogramEntryFactory`, update `Patient` and `ModelHelper`**

Create `app/Models/OdontogramEntry.php` with `HasAuthor, HasFactory, SoftDeletes`, `#[Fillable([...])]`, `casts(): array` casting `scope => OdontogramScope::class`, `arch => DentalArch::class`, `surfaces => 'array'`, `condition_code => OdontogramCondition::class`, `status => OdontogramStatus::class`, `tooth_number => 'integer'`, `bridge_to_tooth => 'integer'`, and all BelongsTo relations using `->withTrashed()` on soft-deletable parents (`patient`, `branch`, `appointment`, `service`, `dentist`, `assistant`).
Create `database/factories/OdontogramEntryFactory.php` with sensible defaults and helper states (`finding()`, `preExisting()`, `planned()`, `completed()`, `superseded()`).
Add `odontogramEntries(): HasMany` to `app/Models/Patient.php`.
Add `'OdontogramEntry'` to `$defaultExclusions` in `app/Support/ModelHelper.php`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Models/OdontogramEntryModelTest.php`
Expected: PASS (3 passed).

---

### Task 2: FDI Anatomy & 5-Face Geometry Catalog (`OdontogramGeometryCatalog`) and Visual State Projection (`OdontogramStateResolver`)

**Files:**
- Create: `app/Support/OdontogramGeometryCatalog.php`
- Create: `app/Support/OdontogramStateResolver.php`
- Test: `tests/Feature/Support/OdontogramGeometryAndResolverTest.php`

**Interfaces:**
- Produces:
  - `App\Support\OdontogramGeometryCatalog`:
    - `public static function permanentQuadrants(): array` (`['upper_right' => [18..11], 'upper_left' => [21..28], 'lower_right' => [48..41], 'lower_left' => [31..38]]`)
    - `public static function primaryQuadrants(): array` (`['upper_right' => [55..51], 'upper_left' => [61..65], 'lower_right' => [85..81], 'lower_left' => [71..75]]`)
    - `public static function validToothNumbers(): array<int, int>`
    - `public static function isValidTooth(int $toothNumber): bool`
    - `public static function quadrant(int $toothNumber): int`
    - `public static function position(int $toothNumber): int`
    - `public static function isUpper(int $toothNumber): bool`
    - `public static function isRightSide(int $toothNumber): bool`
    - `public static function isAnterior(int $toothNumber): bool`
    - `public static function isPosterior(int $toothNumber): bool`
    - `public static function isPrimary(int $toothNumber): bool`
    - `public static function sameArch(int $firstTooth, int $secondTooth): bool`
    - `public static function morphology(int $toothNumber): string` (`'incisor' | 'canine' | 'premolar' | 'molar'`)
    - `public static function surfaceMapping(int $toothNumber): array{top: string, bottom: string, left: string, right: string, center: string}`
    - `public static function allowedSurfacesForTooth(int $toothNumber): array<int, string>`
    - `public static function normalizeSurfaces(int $toothNumber, array $surfaces): array<int, string>`
    - `public static function surfacesOverlap(array $first, array $second): bool`
  - `App\Support\OdontogramStateResolver`:
    - `public function resolve(Collection $entries, string $timeline = 'current'): array{teeth: array<int, array<string, mixed>>, bridges: array<int, array<string, mixed>>, archEntries: Collection}`

- [ ] **Step 1: Write the failing geometry and state resolver tests**

Create `tests/Feature/Support/OdontogramGeometryAndResolverTest.php`:

```php
<?php

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Support/OdontogramGeometryAndResolverTest.php`
Expected: FAIL (`Class "App\Support\OdontogramGeometryCatalog" not found`).

- [ ] **Step 3: Implement `App\Support\OdontogramGeometryCatalog`**

Implement quadrant arrays, validation helpers, morphology detection (remembering positions `4` and `5` in primary quadrants `5..8` are molars, whereas in `1..4` they are premolars), exact 4-quadrant-group surface inversion mapping, canonical surface ordering `['V', 'P', 'L', 'M', 'D', 'O', 'I']`, and overlap helper.

- [ ] **Step 4: Implement `App\Support\OdontogramStateResolver`**

For each valid FDI tooth (`11..48`, `51..85`), build:
- `mapping`: polygon -> surface code (`top`, `bottom`, `left`, `right`, `center`)
- `polygons`: for each position, `'surface'`, `'color'` (`#FFFFFF` default, or status hex), `'borderColor'`, `'condition'`, `'status'`, `'entryId'`, `'isSealant'`
- Tooth overlays: `'crown'` (array with `color`, `status`), `'endodontics'` (`color`, `status`), `'implant'` (`color`, `status`), `'extraction'` (`color`, `status`), `'missing'` (`color`, `status`), `'fracture'` (`color`, `status`), plus boolean flags `'hasEndodontics'`, `'hasCrown'`, `'hasImplant'`, `'isExtracted'`, `'isMissing'`, `'hasFracture'`, `'isBlocked'` (true when `isMissing` or `extraction` status is `Completed`). Note: when `isBlocked` is true AND `hasImplant` is true, the tooth has an active implant so `isBlocked` remains true for surface fillings unless implant crown, wait — per spec section 6: `missing` / `extraction (completed)` blocks surface treatments and crowns, while allowing `implant` or `bridge`. Keep `isBlocked = $isMissing || $isCompletedExtraction` and expose `allowsImplant = $isBlocked && ! $hasImplant` so UI/Action logic is crystal clear.
- `bridges`: list of active `Range` entries with `fromTooth`, `toTooth`, `arch` (`'upper'` or `'lower'`), `color`, `status`, `entryId`.
- `archEntries`: active `Arch` / `Mouth` entries.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Support/OdontogramGeometryAndResolverTest.php`
Expected: PASS (all cases).

---

### Task 3: Transactional Clinical Actions & Rules (`RecordOdontogramEntryAction`, `CompleteOdontogramEntryAction`, `CancelOdontogramEntryAction`)

**Files:**
- Create: `app/Data/Odontogram/RecordOdontogramEntryData.php`
- Create: `app/Actions/Odontogram/RecordOdontogramEntryAction.php`
- Create: `app/Actions/Odontogram/CompleteOdontogramEntryAction.php`
- Create: `app/Actions/Odontogram/CancelOdontogramEntryAction.php`
- Test: `tests/Feature/Actions/OdontogramActionsTest.php`

**Interfaces:**
- Produces:
  - `App\Data\Odontogram\RecordOdontogramEntryData`: readonly DTO with `patientId`, `conditionCode`, `status`, `scope`, `toothNumber`, `bridgeToTooth`, `arch`, `surfaces`, `serviceId`, `branchId`, `appointmentId`, `dentistId`, `assistantId`, `notes`, and `fromArray(array $data): self`
  - `App\Actions\Odontogram\RecordOdontogramEntryAction::handle(RecordOdontogramEntryData|array $data): OdontogramEntry`
  - `App\Actions\Odontogram\CompleteOdontogramEntryAction::handle(OdontogramEntry $entry, array $attributes = []): OdontogramEntry`
  - `App\Actions\Odontogram\CancelOdontogramEntryAction::handle(OdontogramEntry $entry): OdontogramEntry`

- [ ] **Step 1: Write the failing action tests covering all business rules and Review Focus edge cases**

Create `tests/Feature/Actions/OdontogramActionsTest.php`:

```php
<?php

use App\Actions\Odontogram\CancelOdontogramEntryAction;
use App\Actions\Odontogram\CompleteOdontogramEntryAction;
use App\Actions\Odontogram\RecordOdontogramEntryAction;
use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
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
        'surfaces' => ['M', 'O'],
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
    // Sealant on anterior incisor or non-O surface fails
    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Surface->value,
        'tooth_number' => 11,
        'surfaces' => ['I'],
        'condition_code' => OdontogramCondition::Sealant->value,
        'status' => OdontogramStatus::Completed->value,
        'service_id' => $this->service->id,
    ]))->toThrow(ValidationException::class);

    // Bridge across upper and lower arch fails
    expect(fn () => app(RecordOdontogramEntryAction::class)->handle([
        'patient_id' => $this->patient->id,
        'scope' => OdontogramScope::Range->value,
        'tooth_number' => 14,
        'bridge_to_tooth' => 44,
        'condition_code' => OdontogramCondition::Bridge->value,
        'status' => OdontogramStatus::Planned->value,
        'service_id' => $this->service->id,
    ]))->toThrow(ValidationException::class);

    // Planned without service fails
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Actions/OdontogramActionsTest.php`
Expected: FAIL (`Class "App\Actions\Odontogram\RecordOdontogramEntryAction" not found`).

- [ ] **Step 3: Implement `RecordOdontogramEntryData`, `RecordOdontogramEntryAction`, `CompleteOdontogramEntryAction`, and `CancelOdontogramEntryAction`**

Implement strict validation throwing `Illuminate\Validation\ValidationException::withMessages(...)`:
- Validate tooth number via `OdontogramGeometryCatalog::isValidTooth()` when scope is `Surface`, `Tooth`, or `Range`.
- Validate `service_id` required when `status` is `Planned` or `Completed`.
- Validate `Sealant` only allowed on posterior teeth (`OdontogramGeometryCatalog::isPosterior()`) with surfaces `['O']`.
- Validate `Bridge` (`Range`) requires `bridge_to_tooth`, `bridge_to_tooth !== tooth_number`, and `OdontogramGeometryCatalog::sameArch()`.
- Check blocked tooth status (`Missing` or `Completed` `Extraction`): throw `ValidationException` unless `condition_code` is `Implant` or `Bridge`.
- Wrap creation/completion + superseding overlapping `Finding` entries inside `DB::transaction()`.
  - Supersede rule:
    - If `Surface`: find active `Finding` entries for `(patient_id, tooth_number, scope=Surface)` whose `surfaces` overlap via `OdontogramGeometryCatalog::surfacesOverlap()`, plus any `Finding` `Fracture`? Keep `Finding` `Surface` entries that overlap; if `Crown` or `Extraction` becomes `Completed`, supersede all `Finding` entries on that `tooth_number` (both `Surface` and `Tooth` `Fracture`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Actions/OdontogramActionsTest.php`
Expected: PASS (4 passed).

---

### Task 4: Anatomical Tooth SVG Blades and `PatientOdontogram` Livewire + Filament 5 Component

**Files:**
- Create: `resources/views/components/odontogram/tooth-svg.blade.php`
- Create: `resources/views/components/odontogram/surface-box.blade.php`
- Create: `app/Livewire/Patients/PatientOdontogram.php`
- Create: `resources/views/livewire/patients/patient-odontogram.blade.php`
- Test: `tests/Feature/Livewire/PatientOdontogramComponentTest.php`

**Interfaces:**
- Produces:
  - `<x-odontogram.tooth-svg :tooth="$toothNumber" :state="$toothState" :inverted="$isLowerRow" />`
  - `<x-odontogram.surface-box :tooth="$toothNumber" :state="$toothState" />`
  - `App\Livewire\Patients\PatientOdontogram` (extends `Livewire\Component` implementing `Filament\Actions\Contracts\HasActions`, `Filament\Schemas\Contracts\HasSchemas`, `Filament\Tables\Contracts\HasTable`; public `Patient $record`, `string $dentitionTab = 'permanent'`, `string $timelineMode = 'current'`, `string $interactionMode = 'mode_b'`, `string $quickCondition = 'caries'`)
  - Methods: `recordQuickFinding(int $toothNumber, ?string $surface = null, ?string $conditionCode = null): void`, `registerTreatmentAction(): Action`, `registerMouthProcedureAction(): Action`, `table(Table $table): Table`

- [ ] **Step 1: Write the failing Livewire component test**

Create `tests/Feature/Livewire/PatientOdontogramComponentTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Livewire/PatientOdontogramComponentTest.php`
Expected: FAIL (`Class "App\Livewire\Patients\PatientOdontogram" not found`).

- [ ] **Step 3: Create the two Blade SVG components (`tooth-svg.blade.php` and `surface-box.blade.php`)**

- `resources/views/components/odontogram/tooth-svg.blade.php`:
  - Renders the anatomical silhouette SVG (root + crown) for `'incisor'`, `'canine'`, `'premolar'`, and `'molar'`.
  - When `$inverted` is `true` (lower quadrants `4`, `3`, `8`, `7`), flips vertically (`transform="translate(0, 76) scale(1, -1)"` or distinct coordinate paths) so roots point downward away from the occlusal center line.
  - Draws overlays on the anatomical tooth: blue/red/green crown tint/stroke when `crown` active, root canal line(s) for `endodontics`, implant screw in root area for `implant`, `X` cross for `extraction`/`missing` (dimmed opacity on `missing`/`completed extraction`), and red zig-zag for `fracture`.
- `resources/views/components/odontogram/surface-box.blade.php`:
  - Renders the 5-polygon interactive SVG (`viewBox="0 0 100 100"`): `top`, `bottom`, `left`, `right`, `center`.
  - Each polygon carries `data-tooth="{{ $tooth }}" data-surface="{{ $surfaceCode }}"`, fills with its resolved color (or highlights ring when selected in Alpine.js `isSelectedSurface(tooth, surface)`), and displays `S` for `sealant`, outer circle for `crown`, `IMP` for `implant`, `X` for `extraction`/`missing`, and zig-zag for `fracture`.

- [ ] **Step 4: Create `App\Livewire\Patients\PatientOdontogram`**

- Uses `InteractsWithActions`, `InteractsWithSchemas`, and `InteractsWithTable` (matching Filament 5's `TableWidget` contract stack).
- Authorizes every mutation against `auth()->user()?->can('update_patients') ?? false` (sending danger notification if unauthorized).
- `getOdontogramProjectionProperty(): array` resolves `OdontogramStateResolver` for `$this->record->odontogramEntries()->orderBy('id')->get()` and `$this->timelineMode`.
- `registerTreatmentAction()`: modal form with `condition_code`, `service_id` (required for `planned`/`completed`), `status` (`Planned` or `Completed`), `appointment_id` (patient's appointments, `->live()` autofilling `branch_id` and `dentist_id` if empty), `branch_id`, `dentist_id` (users with Dentist role or all users fallback, defaulting to current user if Dentist), `assistant_id` (users with DentalAssistant role), `notes`, and hidden/disabled context summary showing Tooth / Surfaces / Bridge range.
- `registerMouthProcedureAction()`: modal form with `scope` (`Arch` or `Mouth`), `arch` (`Upper`, `Lower` when `Arch`; `Full` when `Mouth`), `condition_code` (`Prophylaxis`, etc.), `service_id`, `status`, `appointment_id`, `branch_id`, `dentist_id`, `assistant_id`, `notes`.
- `table(Table $table): Table`: queries `OdontogramEntry::query()->where('patient_id', $this->record->id)->with(['service', 'dentist', 'assistant', 'branch', 'appointment'])` sorted by `created_at` desc, `id` desc. Columns: Date (`created_at`), Piece/Arch, Surfaces, Condition (`badge`), Service, Status (`badge`), Dentist, Assistant, Notes. Row actions: `completeEntry` (`Action::make('completeEntry')`, visible when `status === OdontogramStatus::Planned` and user can `update_patients`), `cancelEntry` (visible on active entries when user can `update_patients`), `deleteEntry` (`DeleteAction::make('deleteEntry')` soft-deleting, visible when user can `update_patients`).

- [ ] **Step 5: Create `resources/views/livewire/patients/patient-odontogram.blade.php`**

- Build the layout matching `image.png` + user's confirmed mockup:
  - Top bar: Permanent vs Primary tabs (`wire:click="$set('dentitionTab', ...)"` or Alpine + Livewire sync), Timeline switcher (`Odontograma Actual` vs `Odontograma Inicial`), and `{{ $this->registerMouthProcedureAction }}`.
  - Mode A / Mode B toolbar + color legend (`Rojo`: Hallazgo / Planificado, `Azul`: Realizado, `Verde`: Preexistente).
  - In **Mode A**: clicking any polygon calls `$wire.recordQuickFinding(tooth, surface, activeQuickCondition)` (or tooth-level click for tooth-scope conditions like `missing`, `fracture`).
  - In **Mode B**: clicking polygons on a tooth toggles multi-surface selection (`M-O-D`) in Alpine.js with a floating/toolbar action bar `"Registrar tratamiento en pieza X (caras)"` that triggers `$wire.mountAction('registerTreatment', { scope: 'surface', tooth_number: ..., surfaces: ... })`, plus a quick tooth-level button / bridge-selection toggle (`"Marcar puente"`).
  - Quadrant rows:
    - Permanent tab: Upper row `18..11 | 21..28` (with upper bridge brackets `┌───┐` above, anatomical tooth root-up, FDI number, 5-face box) and Lower row `48..41 | 31..38` (5-face box, FDI number, anatomical tooth root-down, lower bridge brackets `└───┘` below).
    - Primary tab: Upper row `55..51 | 61..65` and Lower row `85..81 | 71..75` centered with the exact same mirror structure.
  - Below the chart: `{{ $this->table }}` and `<x-filament-actions::modals />`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Livewire/PatientOdontogramComponentTest.php`
Expected: PASS (3 passed).

---

### Task 5: Integrate into `PatientInfolist`, Add Spanish Translations, Build Assets, and Full Verification

**Files:**
- Modify: `app/Filament/Resources/Patients/Schemas/PatientInfolist.php:91-127`
- Modify: `lang/es.json`
- Test: `tests/Feature/Filament/PatientResourceTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Patients\PatientOdontogram` via `Filament\Schemas\Components\Livewire::make(PatientOdontogram::class)->columnSpanFull()`

- [ ] **Step 1: Write the failing test in `PatientResourceTest.php` verifying the Odontogram tab renders on `ViewPatient`**

Append to `tests/Feature/Filament/PatientResourceTest.php`:

```php
it('renders the odontogram component inside the view patient page', function () {
    $patient = Patient::factory()->create();

    Livewire::test(ViewPatient::class, ['record' => $patient->id])
        ->assertOk()
        ->assertSee(__('Odontogram'))
        ->assertSeeLivewire(\App\Livewire\Patients\PatientOdontogram::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Filament/PatientResourceTest.php --filter="renders the odontogram"`
Expected: FAIL (`Did not find [Odontogram]` / `Livewire component not found`).

- [ ] **Step 3: Add the `Odontogram` tab to `PatientInfolist.php` and add translations to `lang/es.json`**

In `app/Filament/Resources/Patients/Schemas/PatientInfolist.php`, add a 3rd `Tab::make(__('Odontogram'))` with `->icon('heroicon-o-clipboard-document-list')` (or `Heroicon::OutlinedClipboardDocumentList`) containing `Livewire::make(PatientOdontogram::class)->columnSpanFull()`.
Add all new English->Spanish translation pairs to `lang/es.json` (ensuring valid JSON).

- [ ] **Step 4: Build frontend assets (`npm run build`), format code with Pint (`vendor/bin/pint --dirty --format agent`), and run tests**

Run:
1. `npm run build`
2. `vendor/bin/pint --dirty --format agent`
3. `php artisan test --compact tests/Feature/Models/OdontogramEntryModelTest.php tests/Feature/Support/OdontogramGeometryAndResolverTest.php tests/Feature/Actions/OdontogramActionsTest.php tests/Feature/Livewire/PatientOdontogramComponentTest.php tests/Feature/Filament/PatientResourceTest.php tests/Feature/LocalizationTest.php`
Expected: PASS on all tests, zero Pint formatting issues.
