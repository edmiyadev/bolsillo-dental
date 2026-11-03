# Design Doc: Patient Demographics Expansion & Medical History Entity

## Intent & Why
Enhance the `Patient` module in Bolsillo Dental with essential clinical and contact demographics (residential phone, work phone, health insurance, birth date, gender, and profile photo) and establish a dedicated 1:1 `PatientMedicalHistory` entity. This allows dental clinics to record critical patient alerts, anamnesis information, and general clinical observations in an integrated, structured manner.

## Goals
- Update the existing tenant migration for `patients` to include: `residential_phone`, `work_phone`, `health_insurance`, `birth_date`, `gender`, and `photo_url`.
- Implement a `Gender` backed enum with Spanish translations for patient sex selection.
- Create a tenant migration for `patient_medical_histories` with `patient_id` (foreign key, unique), `critical_alerts` (`json`), `anamnesis_data` (`json`), and `general_observations` (`text`).
- Create the `PatientMedicalHistory` Eloquent model with proper array casts and bidirectional 1:1 relationship (`Patient::medicalHistory()`, `PatientMedicalHistory::patient()`).
- Reorganize `PatientForm` and `PatientInfolist` in Filament into two clean tabs:
  1. **General Information (Datos generales)**: Contact numbers, address, email, document identification, birth date, gender, insurance, and photo upload.
  2. **Medical History (Historial Médico)**: Embedded relationship fields for critical alerts (`TagsInput`), anamnesis data (`KeyValue`), and general observations (`Textarea`).
- Update `PatientFactory` and `PatientResourceTest` to ensure all fields and relations are covered and tests pass.

## Non-Goals & Anti-Goals
- Do not create a separate tenant migration for altering `patients` (per user directive: project is in development mode; update the initial patient migration directly).
- Do not create a separate standalone Filament resource or sub-page navigation for medical history; manage it directly in the Patient tabs.
- Do not add complex clinical dental odontograms in this step (reserved for future appointment/treatment features).

## Constraints
- Laravel 12 on PHP 8.4 in Docker container (`docker compose -f compose.dev.yaml exec -T app ...`).
- Filament v5 using schemas (`Filament\Schemas\Components\Tabs`, `Filament\Schemas\Components\Tabs\Tab`, etc.).
- Multi-tenancy powered by Stancl Tenancy with migrations stored in `database/migrations/tenant/`.

## Approaches Considered
1. **Approach 1 (Selected): Embedded Tabs in `PatientForm` & `PatientInfolist` with `FileUpload`**
   - Patients form has two tabs: "Datos generales" and "Historial Médico".
   - Medical history fields are bound to the `medicalHistory` relationship.
   - Profile photo uses Filament `FileUpload` (stored in public/app storage, avatar preview).
   - *Rationale*: Unified clinical workflow; dental reception and doctors can review or edit both demographic and medical history without context switching.
2. **Approach 2 (Rejected): Separate Sub-navigation Pages**
   - Independent Edit pages for Patient and Medical History.
   - *Trade-off*: Slower workflow with disconnected forms and multiple submit buttons.
3. **Approach 3 (Rejected): RelationManager Table**
   - Display medical history in a RelationManager table under the patient view.
   - *Trade-off*: Clunky for a strict 1:1 relationship where there is only ever one medical history per patient.

## Verification
- Reset/run tenant migrations (`php artisan migrate:fresh` on tenants or via test runner).
- Run Pest test suite: `docker compose -f compose.dev.yaml exec -T app php artisan test tests/Feature/Filament/PatientResourceTest.php`.
- Code formatting with Pint: `vendor/bin/pint --format agent`.
