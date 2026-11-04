<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use App\Support\MedicalHistoryCatalog;
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

        $medicalData = [];
        $catalog = MedicalHistoryCatalog::questions();

        foreach ($catalog as $key => $config) {
            $isPositive = match ($key) {
                'allergies' => $hasAllergies,
                'hypertension' => $hasHypertension,
                'diabetes' => $hasDiabetes,
                'under_medical_treatment' => $underTreatment,
                'taking_medication' => $takesMeds,
                default => false,
            };

            $detail = null;
            if ($isPositive && $config['has_detail']) {
                $detail = match ($key) {
                    'allergies' => fake()->randomElement(['Penicilina', 'Látex', 'Ibuprofeno']),
                    'hypertension' => 'Controlada con medicación',
                    'diabetes' => 'Tipo 2',
                    'under_medical_treatment' => 'Control médico de rutina',
                    'taking_medication' => 'Medicamentos recetados',
                    default => null,
                };
            }

            $medicalData[$key] = [
                'value' => $isPositive,
                'detail' => $detail,
                'is_critical' => $config['is_critical'],
                'has_detail' => $config['has_detail'],
            ];
        }

        return [
            'patient_id' => Patient::factory(),
            'medical_data' => $medicalData,
            'general_observations' => fake()->optional()->paragraph(),
        ];
    }
}
