<?php

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
