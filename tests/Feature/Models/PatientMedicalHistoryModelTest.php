<?php

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('patient medical history stores structured critical alerts and anamnesis with belongsTo patient', function () {
    $patient = Patient::factory()->create();
    $history = PatientMedicalHistory::create([
        'patient_id' => $patient->id,
        'critical_alerts' => [
            'allergies' => ['value' => true, 'detail' => 'Penicilina'],
            'hypertension' => ['value' => false, 'detail' => null],
        ],
        'anamnesis_data' => [
            'under_medical_treatment' => ['value' => true, 'detail' => 'Cardiólogo Dr. Soto'],
            'tobacco_use' => ['value' => false, 'detail' => null],
        ],
        'general_observations' => 'Paciente refiere molestia en molar superior derecho.',
    ]);

    expect($history->patient->id)->toBe($patient->id)
        ->and($history->critical_alerts['allergies'])->toBe(['value' => true, 'detail' => 'Penicilina'])
        ->and($history->anamnesis_data['under_medical_treatment'])->toBe(['value' => true, 'detail' => 'Cardiólogo Dr. Soto'])
        ->and($history->general_observations)->toContain('molar superior derecho')
        ->and($patient->fresh()->medicalHistory->id)->toBe($history->id);
});

test('patient medical history provides predefined question catalogs and active helpers', function () {
    $criticalCatalogs = PatientMedicalHistory::criticalAlertQuestions();
    $anamnesisCatalogs = PatientMedicalHistory::anamnesisQuestions();

    expect($criticalCatalogs)->toHaveKeys(['allergies', 'hypertension', 'diabetes', 'heart_disease', 'bleeding_disorders', 'pregnancy_or_breastfeeding', 'infectious_diseases'])
        ->and($anamnesisCatalogs)->toHaveKeys(['under_medical_treatment', 'taking_medication', 'recent_hospitalization', 'respiratory_problems', 'gastric_problems', 'tobacco_use', 'adverse_anesthesia_reaction', 'fainting_or_dental_anxiety']);

    $history = new PatientMedicalHistory([
        'critical_alerts' => [
            'allergies' => ['value' => true, 'detail' => 'Penicilina'],
            'hypertension' => ['value' => false],
            'diabetes' => ['value' => true, 'detail' => 'Tipo 2'],
        ],
        'anamnesis_data' => [
            'under_medical_treatment' => ['value' => true, 'detail' => 'Tratamiento hormonal'],
            'tobacco_use' => ['value' => false],
        ],
    ]);

    $activeAlerts = $history->activeCriticalAlerts();
    expect($activeAlerts)->toHaveCount(2)
        ->and($activeAlerts[0]['key'])->toBe('allergies')
        ->and($activeAlerts[0]['detail'])->toBe('Penicilina')
        ->and($activeAlerts[0]['summary'])->toContain('Penicilina')
        ->and($activeAlerts[1]['key'])->toBe('diabetes')
        ->and($activeAlerts[1]['detail'])->toBe('Tipo 2');

    $activeAnamnesis = $history->activeAnamnesis();
    expect($activeAnamnesis)->toHaveCount(1)
        ->and($activeAnamnesis[0]['key'])->toBe('under_medical_treatment')
        ->and($activeAnamnesis[0]['detail'])->toBe('Tratamiento hormonal');
});

test('patient_id has unique constraint on patient_medical_histories', function () {
    $patient = Patient::factory()->create();
    PatientMedicalHistory::create([
        'patient_id' => $patient->id,
        'general_observations' => 'Primer historial',
    ]);

    expect(fn () => PatientMedicalHistory::create([
        'patient_id' => $patient->id,
        'general_observations' => 'Segundo historial duplicado',
    ]))->toThrow(Exception::class);
});
