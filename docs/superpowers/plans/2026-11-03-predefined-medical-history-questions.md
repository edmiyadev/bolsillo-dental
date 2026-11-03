# Predefined Medical History Questions with Conditional Detail Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the patient medical history system to use predefined Yes/No questions divided between critical alerts (`critical_alerts`) and general anamnesis (`anamnesis_data`), providing conditional detail text inputs when answering "Sí", plus full display in Filament views and model helpers for quick cross-application access.

**Architecture:** 
- `PatientMedicalHistory` defines standard question catalogs (`criticalAlertQuestions()` and `anamnesisQuestions()`) with identifiers, labels, short labels (for badges), and detail placeholders.
- Eloquent cast arrays store structured questions: `['question_key' => ['value' => bool, 'detail' => ?string]]`.
- Model helper methods (`activeCriticalAlerts()`, `activeAnamnesis()`) extract positive conditions for display anywhere in the system (appointment cards, headers, infolist).
- Filament `PatientForm` uses responsive sections with `Toggle` switches and conditionally visible `TextInput` details (`visible(fn (Get $get) => (bool) $get(...))`).
- Filament `PatientInfolist` displays active critical alerts with high-visibility badges and anamnesis summaries.

**Tech Stack:** Laravel 12, PHP 8.4, Filament v5, Pest PHP.

---

## Catalog of Predefined Questions

### Critical Alerts (`critical_alerts`)
1. `allergies`: Alergia a medicamentos (penicilina, anestésicos) o materiales (látex)
2. `hypertension`: Hipertensión arterial
3. `diabetes`: Diabetes (Tipo 1 o 2)
4. `heart_disease`: Cardiopatías, problemas cardíacos o marcapasos
5. `bleeding_disorders`: Trastornos de coagulación o toma de anticoagulantes
6. `pregnancy_or_breastfeeding`: Embarazo o período de lactancia
7. `infectious_diseases`: Enfermedades infectocontagiosas (Hepatitis, VIH)

### General Anamnesis (`anamnesis_data`)
1. `under_medical_treatment`: ¿Está bajo tratamiento médico actualmente?
2. `taking_medication`: ¿Toma medicamentos de forma continua?
3. `recent_hospitalization`: ¿Ha tenido cirugías u hospitalizaciones en los últimos 2 años?
4. `respiratory_problems`: ¿Padece de problemas respiratorios (asma, dificultad respiratoria)?
5. `gastric_problems`: ¿Padece de problemas gástricos (reflujo severo, úlceras)?
6. `tobacco_use`: ¿Fuma o consume tabaco / vapeo?
7. `adverse_anesthesia_reaction`: ¿Ha tenido reacciones adversas o complicaciones con anestesia dental previa?
8. `fainting_or_dental_anxiety`: ¿Tiende a marearse, desmayarse o sufre de ansiedad dental severa?

---

## Tasks

### Task 1: Question Catalog & Model Helpers in `PatientMedicalHistory`

**Files:**
- Modify: `app/Models/PatientMedicalHistory.php`
- Modify: `database/factories/PatientMedicalHistoryFactory.php`
- Modify: `tests/Feature/Models/PatientMedicalHistoryModelTest.php`

- [ ] **Step 1.1:** Write tests in `tests/Feature/Models/PatientMedicalHistoryModelTest.php` verifying:
  - Catalogs `criticalAlertQuestions()` and `anamnesisQuestions()` return expected question keys and metadata.
  - `activeCriticalAlerts()` returns an array with label and detail for conditions with `'value' => true`.
  - `activeAnamnesis()` returns an array with label and detail for conditions with `'value' => true`.
- [ ] **Step 1.2:** Run tests to confirm failure.
- [ ] **Step 1.3:** Implement catalog methods and helper methods in `app/Models/PatientMedicalHistory.php`.
- [ ] **Step 1.4:** Update `database/factories/PatientMedicalHistoryFactory.php` to generate valid question structures.
- [ ] **Step 1.5:** Run tests to confirm they pass.
- [ ] **Step 1.6:** Commit changes: `feat: add predefined medical history questions catalog and helpers`.

---

### Task 2: Filament Form with Predefined Toggles and Conditional Detail Inputs

**Files:**
- Modify: `app/Filament/Resources/Patients/Schemas/PatientForm.php`
- Modify: `lang/es.json`
- Modify: `tests/Feature/Filament/PatientResourceTest.php`

- [ ] **Step 2.1:** Write feature test in `tests/Feature/Filament/PatientResourceTest.php` checking:
  - Can save patient with predefined critical alerts and anamnesis data containing both boolean values and detail text.
  - Can retrieve and edit existing boolean values and details in form state.
- [ ] **Step 2.2:** Run tests to observe failure.
- [ ] **Step 2.3:** Update `PatientForm.php` "Historial Médico" tab:
  - Create "Alertas Críticas" Section iterating over `PatientMedicalHistory::criticalAlertQuestions()`, adding a `Toggle` and conditionally visible `TextInput` (`->visible(fn (Get $get) => (bool) $get(...))`).
  - Create "Cuestionario de Anamnesis" Section iterating over `PatientMedicalHistory::anamnesisQuestions()`, adding a `Toggle` and conditionally visible `TextInput`.
  - Keep `general_observations` Textarea at the bottom.
- [ ] **Step 2.4:** Add any missing translation strings to `lang/es.json`.
- [ ] **Step 2.5:** Run tests to confirm they pass.
- [ ] **Step 2.6:** Commit changes: `feat: implement predefined toggles and conditional details in patient medical history form`.

---

### Task 3: Filament Infolist with Active Critical Alert Badges and Anamnesis Details

**Files:**
- Modify: `app/Filament/Resources/Patients/Schemas/PatientInfolist.php`
- Modify: `tests/Feature/Filament/PatientResourceTest.php`

- [ ] **Step 3.1:** Write test verifying that `PatientInfolist` correctly formats active critical alerts (showing badges/details) and anamnesis answers.
- [ ] **Step 3.2:** Update `PatientInfolist.php` in the "Historial Médico" tab:
  - Display active critical alerts with danger-styled badges / repeat list showing label and detail text.
  - Display active anamnesis conditions and details.
  - Show clear placeholders when no alerts/conditions are present.
- [ ] **Step 3.3:** Run tests to confirm they pass.
- [ ] **Step 3.4:** Commit changes: `feat: enhance patient infolist with critical alert badges and anamnesis details`.

---

### Task 4: Suite Verification and Code Formatting

- [ ] **Step 4.1:** Run `vendor/bin/pint --dirty --format agent` to format modified PHP files.
- [ ] **Step 4.2:** Run tests with `php artisan test tests/Feature/Filament/PatientResourceTest.php tests/Feature/Models/PatientMedicalHistoryModelTest.php tests/Feature/LocalizationTest.php`.
- [ ] **Step 4.3:** Commit any formatting or final touches.
