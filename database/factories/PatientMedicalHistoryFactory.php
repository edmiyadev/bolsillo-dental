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
        return [
            'patient_id' => Patient::factory(),
            'critical_alerts' => fake()->randomElements([
                'Alergia a la Penicilina',
                'Hipertensión arterial',
                'Diabetes Mellitus Tipo 2',
                'Asma bronquial',
                'Uso de anticoagulantes',
            ], fake()->numberBetween(0, 2)),
            'anamnesis_data' => [
                'diabetes' => fake()->randomElement(['No', 'Controlada con metformina']),
                'alergias' => fake()->randomElement(['Ninguna', 'Penicilina', 'Látex']),
                'cirugias_previas' => fake()->randomElement(['Ninguna', 'Apendicectomía', 'Extracción terceros molares']),
                'medicamentos_actuales' => fake()->randomElement(['Ninguno', 'Losartán 50mg', 'Omeprazol 20mg']),
            ],
            'general_observations' => fake()->optional()->paragraph(),
        ];
    }
}
