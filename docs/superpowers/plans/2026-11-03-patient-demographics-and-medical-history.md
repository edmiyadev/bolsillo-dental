# Patient Demographics & Medical History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand `Patient` with residential/work phones, health insurance, birth date, gender, and profile photo, and implement a 1:1 `PatientMedicalHistory` entity managed via Filament tabs.

**Architecture:** Update the existing tenant migration for `patients` directly (development mode); create a new tenant migration and Eloquent model for `patient_medical_histories` storing JSON alerts and anamnesis with text observations; integrate both into `PatientForm` and `PatientInfolist` using Filament tabs and relationship-bound components.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL, Stancl Tenancy, Filament v5 (Schemas & Forms), Pest PHP.

**Spec:** `docs/superpowers/specs/2026-11-03-patient-fields-and-medical-history-design.md`

## Global Constraints
- Target PHP version: 8.4 with constructor property promotion, strict types, and `#[Fillable]` attribute where applicable.
- Existing migration `database/migrations/tenant/2026_10_22_161910_create_patients_table.php` is modified directly (no new alter migration for patients table).
- Multi-tenancy migrations belong in `database/migrations/tenant/`.
- All Artisan and test commands run via Docker container: `docker compose -f compose.dev.yaml exec -T app php artisan ...`.
- Run Pint code formatter on changed files: `vendor/bin/pint --dirty --format agent`.

## Review Focus
- Attempting to associate a second `PatientMedicalHistory` to the same patient must fail due to unique constraint on `patient_id`.
- Deleting a `Patient` must cascade or cleanly handle the associated `PatientMedicalHistory`.
- `critical_alerts` and `anamnesis_data` must properly serialize and deserialize as PHP arrays when saving and fetching.
- `gender` must validate against valid `Gender` enum values.
- `photo_url` upload must handle null values gracefully without breaking form rendering or infolist viewing.

---

### Task 1: Gender Enum

**Files:**
- Create: `app/Enums/Gender.php`
- Test: `tests/Unit/Enums/GenderTest.php`

**Interfaces:**
- Produces: `App\Enums\Gender` enum with cases `Male = 'male'`, `Female = 'female'`, `Other = 'other'`, implementing `Filament\Support\Contracts\HasLabel`.

- [ ] **Step 1: Write the unit test for Gender enum**

```php
// tests/Unit/Enums/GenderTest.php
test('gender enum has expected cases and labels', function () {
    expect(Gender::cases())->toHaveCount(3)
        ->and(Gender::Male->value)->toBe('male')
        ->and(Gender::Female->value)->toBe('female')
        ->and(Gender::Other->value)->toBe('other')
        ->and(Gender::Male->getLabel())->toBe(__('Male'))
        ->and(Gender::Female->getLabel())->toBe(__('Female'))
        ->and(Gender::Other->getLabel())->toBe(__('Other'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=GenderTest`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement `Gender` enum in `app/Enums/Gender.php`**

```php
namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Male => __('Male'),
            self::Female => __('Female'),
            self::Other => __('Other'),
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=GenderTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/Gender.php tests/Unit/Enums/GenderTest.php
git commit -m "feat(patient): add Gender backed enum with HasLabel support"
```

---

### Task 2: Patient Table Migration & Model Updates

**Files:**
- Modify: `database/migrations/tenant/2026_10_22_161910_create_patients_table.php`
- Modify: `app/Models/Patient.php`
- Test: `tests/Feature/Models/PatientModelTest.php`

**Interfaces:**
- Consumes: `App\Enums\Gender`
- Produces: `Patient` attributes: `residential_phone`, `work_phone`, `health_insurance`, `birth_date`, `gender`, `photo_url`; casts for `gender => Gender::class` and `birth_date => 'date'`; relation `medicalHistory(): HasOne`.

- [ ] **Step 1: Write model test verifying attributes and relationship**

```php
// tests/Feature/Models/PatientModelTest.php
test('patient has new demographic attributes and medical history relation', function () {
    $patient = Patient::factory()->create([
        'residential_phone' => '8095551111',
        'work_phone' => '8095552222',
        'health_insurance' => 'Humano Seguros',
        'birth_date' => '1990-05-15',
        'gender' => Gender::Male,
        'photo_url' => 'patients/photos/sample.jpg',
    ]);

    expect($patient->residential_phone)->toBe('8095551111')
        ->and($patient->work_phone)->toBe('8095552222')
        ->and($patient->health_insurance)->toBe('Humano Seguros')
        ->and($patient->birth_date->format('Y-m-d'))->toBe('1990-05-15')
        ->and($patient->gender)->toBe(Gender::Male)
        ->and($patient->photo_url)->toBe('patients/photos/sample.jpg')
        ->and($patient->medicalHistory())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=PatientModelTest`
Expected: FAIL.

- [ ] **Step 3: Update `2026_10_22_161910_create_patients_table.php` and `Patient.php`**

In migration:
```php
$table->string('residential_phone')->nullable();
$table->string('work_phone')->nullable();
$table->string('health_insurance')->nullable();
$table->date('birth_date')->nullable();
$table->string('gender')->nullable();
$table->string('photo_url')->nullable();
```

In `Patient.php`:
- Update `#[Fillable]` to include `residential_phone`, `work_phone`, `health_insurance`, `birth_date`, `gender`, `photo_url`.
- Add casts: `'birth_date' => 'date'`, `'gender' => Gender::class`.
- Add relationship: `public function medicalHistory(): HasOne { return $this->hasOne(PatientMedicalHistory::class); }`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=PatientModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/tenant/2026_10_22_161910_create_patients_table.php app/Models/Patient.php tests/Feature/Models/PatientModelTest.php
git commit -m "feat(patient): add demographic columns to patients table and model"
```

---

### Task 3: Patient Medical History Migration & Model

**Files:**
- Create: `database/migrations/tenant/2026_11_03_000001_create_patient_medical_histories_table.php`
- Create: `app/Models/PatientMedicalHistory.php`
- Test: `tests/Feature/Models/PatientMedicalHistoryModelTest.php`

**Interfaces:**
- Produces: `App\Models\PatientMedicalHistory` model with attributes `patient_id`, `critical_alerts`, `anamnesis_data`, `general_observations`; array casts for `critical_alerts` and `anamnesis_data`; relation `patient(): BelongsTo`.

- [ ] **Step 1: Write model test for PatientMedicalHistory**

```php
// tests/Feature/Models/PatientMedicalHistoryModelTest.php
test('patient medical history stores json alerts and anamnesis with belongsTo patient', function () {
    $patient = Patient::factory()->create();
    $history = PatientMedicalHistory::create([
        'patient_id' => $patient->id,
        'critical_alerts' => ['Alergia a la Penicilina', 'Hipertenso'],
        'anamnesis_data' => ['diabetes' => 'No', 'cirugias_previas' => 'Apendicectomía 2018'],
        'general_observations' => 'Paciente refiere molestia en molar superior derecho.',
    ]);

    expect($history->patient->id)->toBe($patient->id)
        ->and($history->critical_alerts)->toBe(['Alergia a la Penicilina', 'Hipertenso'])
        ->and($history->anamnesis_data)->toMatchArray(['diabetes' => 'No'])
        ->and($history->general_observations)->toContain('molar superior derecho')
        ->and($patient->fresh()->medicalHistory->id)->toBe($history->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=PatientMedicalHistoryModelTest`
Expected: FAIL.

- [ ] **Step 3: Create migration and model**

Create `database/migrations/tenant/2026_11_03_000001_create_patient_medical_histories_table.php`:
```php
Schema::create('patient_medical_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->unique()->constrained('patients')->cascadeOnDelete();
    $table->json('critical_alerts')->nullable();
    $table->json('anamnesis_data')->nullable();
    $table->text('general_observations')->nullable();
    $table->timestamps();
});
```

Create `app/Models/PatientMedicalHistory.php`:
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['patient_id', 'critical_alerts', 'anamnesis_data', 'general_observations'])]
class PatientMedicalHistory extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'critical_alerts' => 'array',
            'anamnesis_data' => 'array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test --filter=PatientMedicalHistoryModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/tenant/2026_11_03_000001_create_patient_medical_histories_table.php app/Models/PatientMedicalHistory.php tests/Feature/Models/PatientMedicalHistoryModelTest.php
git commit -m "feat(patient): create patient_medical_histories table and model"
```

---

### Task 4: Filament PatientForm & PatientInfolist with Tabs

**Files:**
- Modify: `app/Filament/Resources/Patients/Schemas/PatientForm.php`
- Modify: `app/Filament/Resources/Patients/Schemas/PatientInfolist.php`
- Modify: `app/Filament/Resources/Patients/Tables/PatientsTable.php`

**Interfaces:**
- Consumes: `App\Enums\Gender`, `Patient`, `PatientMedicalHistory`
- Produces: Updated `PatientForm` with `Tabs` (`Tab::make('General information')`, `Tab::make('Medical history')`), `FileUpload::make('photo_url')->avatar()->image()`, and relationship fields for `medicalHistory`.

- [ ] **Step 1: Update `PatientForm.php` with Tabs and fields**
Organize into:
- Tab 1: "Datos generales"
  - `photo_url` (FileUpload, image, avatar, directory 'patients/avatars')
  - `first_name`, `last_name`
  - `document_type`, `identification_number`
  - `gender` (Select from `Gender::class`)
  - `birth_date` (DatePicker)
  - `phone`, `residential_phone`, `work_phone`
  - `email`
  - `health_insurance`
  - `address`
- Tab 2: "Historial Médico"
  - Wrapped in relationship `'medicalHistory'` schema:
    - `critical_alerts` (TagsInput)
    - `anamnesis_data` (KeyValue)
    - `general_observations` (Textarea)

- [ ] **Step 2: Update `PatientInfolist.php` with Tabs and fields**
Display both demographic details (including `ImageEntry::make('photo_url')->circular()` and birth date / gender / phones) and medical history tab (`TextEntry` badges for critical alerts, KeyValue entry for anamnesis, text for observations).

- [ ] **Step 3: Update `PatientsTable.php`**
Add `ImageColumn::make('photo_url')->circular()` at the beginning of the table.

- [ ] **Step 4: Run tests to verify existing suite doesn't regress**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test tests/Feature/Filament/PatientResourceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Patients/Schemas/PatientForm.php app/Filament/Resources/Patients/Schemas/PatientInfolist.php app/Filament/Resources/Patients/Tables/PatientsTable.php
git commit -m "feat(patient): implement tabs in PatientForm and PatientInfolist with photo and medical history"
```

---

### Task 5: Factory & Feature Test Coverage

**Files:**
- Modify: `database/factories/PatientFactory.php`
- Create: `database/factories/PatientMedicalHistoryFactory.php`
- Modify: `tests/Feature/Filament/PatientResourceTest.php`

**Interfaces:**
- Consumes: All updated patient models and forms.
- Produces: Factory state for complete patient and tests verifying Filament create/edit/view with medical history tabs.

- [ ] **Step 1: Update `PatientFactory` with new default states**
Add fake data for `residential_phone`, `work_phone`, `health_insurance`, `birth_date`, `gender`, `photo_url`.
Create `PatientMedicalHistoryFactory`.

- [ ] **Step 2: Update `PatientResourceTest.php`**
Add assertions for:
- Creating a patient with the new demographic fields and medical history relation data.
- Editing a patient and updating medical history alerts/observations.
- Viewing a patient's medical history tab.

- [ ] **Step 3: Run full feature tests**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test tests/Feature/Filament/PatientResourceTest.php`
Expected: PASS with 100% green assertions.

- [ ] **Step 4: Commit**

```bash
git add database/factories/ tests/Feature/Filament/PatientResourceTest.php
git commit -m "test(patient): add full feature test coverage for demographics and medical history tabs"
```

---

### Task 6: Formatting & Verification

**Files:**
- All modified PHP files

- [ ] **Step 1: Run Pint code formatter**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 2: Run full test suite related to Patients**

Run: `docker compose -f compose.dev.yaml exec -T app php artisan test tests/Feature/Filament/PatientResourceTest.php tests/Feature/Models/PatientModelTest.php tests/Feature/Models/PatientMedicalHistoryModelTest.php tests/Unit/Enums/GenderTest.php`
Expected: All tests PASS.

- [ ] **Step 3: Final commit if needed**

```bash
git add -A
git commit -m "style: apply Pint formatting"
```
