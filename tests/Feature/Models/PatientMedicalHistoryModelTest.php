<?php

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use App\Support\MedicalHistoryCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('patient medical history stores structured unified medical_data with belongsTo patient', function () {
    $patient = Patient::factory()->create();
    $history = PatientMedicalHistory::create([
        'patient_id' => $patient->id,
        'medical_data' => [
            'allergies' => [
                'value' => true,
                'detail' => 'Penicilina',
                'is_critical' => true,
                'has_detail' => true,
            ],
            'hypertension' => [
                'value' => false,
                'detail' => null,
                'is_critical' => true,
                'has_detail' => true,
            ],
            'under_medical_treatment' => [
                'value' => true,
                'detail' => 'Cardiólogo Dr. Soto',
                'is_critical' => false,
                'has_detail' => true,
            ],
            'tobacco_use' => [
                'value' => false,
                'detail' => null,
                'is_critical' => false,
                'has_detail' => true,
            ],
        ],
        'general_observations' => 'Paciente refiere molestia en molar superior derecho.',
    ]);

    expect($history->patient->id)->toBe($patient->id)
        ->and($history->medical_data['allergies'])->toBe([
            'value' => true,
            'detail' => 'Penicilina',
            'is_critical' => true,
            'has_detail' => true,
        ])
        ->and($history->medical_data['under_medical_treatment']['detail'])->toBe('Cardiólogo Dr. Soto')
        ->and($history->general_observations)->toContain('molar superior derecho')
        ->and($patient->fresh()->medicalHistory->id)->toBe($history->id);
});

test('medical history catalog provides questions catalog and extraction helpers', function () {
    $catalog = MedicalHistoryCatalog::questions();

    expect($catalog)->toHaveKeys([
        'allergies',
        'hypertension',
        'diabetes',
        'heart_disease',
        'bleeding_disorders',
        'pregnancy_or_breastfeeding',
        'infectious_diseases',
        'under_medical_treatment',
        'taking_medication',
        'recent_hospitalization',
        'respiratory_problems',
        'gastric_problems',
        'tobacco_use',
        'adverse_anesthesia_reaction',
        'fainting_or_dental_anxiety',
    ])
        ->and($catalog['allergies']['is_critical'])->toBeTrue()
        ->and($catalog['allergies']['has_detail'])->toBeTrue()
        ->and($catalog['under_medical_treatment']['is_critical'])->toBeFalse()
        ->and($catalog['under_medical_treatment']['has_detail'])->toBeTrue();

    $medicalData = [
        'allergies' => ['value' => true, 'detail' => 'Penicilina', 'is_critical' => true],
        'hypertension' => ['value' => false, 'is_critical' => true],
        'diabetes' => ['value' => true, 'detail' => 'Tipo 2', 'is_critical' => true],
        'under_medical_treatment' => ['value' => true, 'detail' => 'Tratamiento hormonal', 'is_critical' => false],
        'tobacco_use' => ['value' => false, 'is_critical' => false],
    ];

    $activeAlerts = MedicalHistoryCatalog::activeCriticalAlerts($medicalData);
    expect($activeAlerts)->toHaveCount(2)
        ->and($activeAlerts[0]['key'])->toBe('allergies')
        ->and($activeAlerts[0]['detail'])->toBe('Penicilina')
        ->and($activeAlerts[0]['summary'])->toContain('Penicilina')
        ->and($activeAlerts[1]['key'])->toBe('diabetes')
        ->and($activeAlerts[1]['detail'])->toBe('Tipo 2');

    $activeConditions = MedicalHistoryCatalog::activeConditions($medicalData);
    expect($activeConditions)->toHaveCount(1)
        ->and($activeConditions[0]['key'])->toBe('under_medical_treatment')
        ->and($activeConditions[0]['detail'])->toBe('Tratamiento hormonal');
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
