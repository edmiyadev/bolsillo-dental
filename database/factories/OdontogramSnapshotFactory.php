<?php

namespace Database\Factories;

use App\Enums\OdontogramSnapshotType;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OdontogramSnapshot>
 */
class OdontogramSnapshotFactory extends Factory
{
    protected $model = OdontogramSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'type' => OdontogramSnapshotType::Initial,
            'title' => 'Odontograma Inicial',
            'notes' => null,
            'payload' => [
                'entries' => [],
            ],
        ];
    }

    public function treatmentCompleted(): static
    {
        return $this->state(fn (): array => [
            'type' => OdontogramSnapshotType::TreatmentCompleted,
            'title' => 'Plan de Tratamiento Completado',
        ]);
    }
}
