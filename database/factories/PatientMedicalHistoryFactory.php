<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientMedicalHistory>
 */
class PatientMedicalHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasAllergies = fake()->boolean(25);
        $hasHypertension = fake()->boolean(20);
        $hasDiabetes = fake()->boolean(15);

        $underTreatment = fake()->boolean(30);
        $takesMeds = fake()->boolean(30);

        return [
            'patient_id' => Patient::factory(),
            'critical_alerts' => [
                'allergies' => [
                    'value' => $hasAllergies,
                    'detail' => $hasAllergies ? fake()->randomElement(['Penicilina', 'Látex', 'Ibuprofeno']) : null,
                ],
                'hypertension' => [
                    'value' => $hasHypertension,
                    'detail' => $hasHypertension ? 'Controlada con medicación' : null,
                ],
                'diabetes' => [
                    'value' => $hasDiabetes,
                    'detail' => $hasDiabetes ? 'Tipo 2' : null,
                ],
                'heart_disease' => ['value' => false, 'detail' => null],
                'bleeding_disorders' => ['value' => false, 'detail' => null],
                'pregnancy_or_breastfeeding' => ['value' => false, 'detail' => null],
                'infectious_diseases' => ['value' => false, 'detail' => null],
            ],
            'anamnesis_data' => [
                'under_medical_treatment' => [
                    'value' => $underTreatment,
                    'detail' => $underTreatment ? 'Control médico de rutina' : null,
                ],
                'taking_medication' => [
                    'value' => $takesMeds,
                    'detail' => $takesMeds ? 'Medicamentos recetados' : null,
                ],
                'recent_hospitalization' => ['value' => false, 'detail' => null],
                'respiratory_problems' => ['value' => false, 'detail' => null],
                'gastric_problems' => ['value' => false, 'detail' => null],
                'tobacco_use' => ['value' => false, 'detail' => null],
                'adverse_anesthesia_reaction' => ['value' => false, 'detail' => null],
                'fainting_or_dental_anxiety' => ['value' => false, 'detail' => null],
            ],
            'general_observations' => fake()->optional()->paragraph(),
        ];
    }
}
