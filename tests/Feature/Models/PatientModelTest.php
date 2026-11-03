<?php

use App\Enums\Gender;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        ->and($patient->medicalHistory())->toBeInstanceOf(HasOne::class);
});
