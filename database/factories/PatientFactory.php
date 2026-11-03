<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\IdentificationType;
use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => fake()->randomElement(IdentificationType::cases()),
            'identification_number' => fake()->unique()->numerify('###########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '809'.fake()->numerify('#######'),
            'residential_phone' => fake()->optional()->passthrough('809'.fake()->numerify('#######')),
            'work_phone' => fake()->optional()->passthrough('809'.fake()->numerify('#######')),
            'health_insurance' => fake()->optional()->randomElement(['Humano Seguros', 'ARS Palic', 'Senasa', 'Universal']),
            'birth_date' => fake()->optional()->date('Y-m-d', '-18 years'),
            'gender' => fake()->optional()->randomElement(Gender::cases()),
            'photo_url' => null,
            'address' => fake()->address(),
        ];
    }

    /**
     * Indicate that the patient has a medical history.
     */
    public function withMedicalHistory(array $attributes = []): static
    {
        return $this->afterCreating(function (Patient $patient) use ($attributes) {
            PatientMedicalHistory::factory()->create(array_merge([
                'patient_id' => $patient->id,
            ], $attributes));
        });
    }
}
