<?php

namespace Database\Factories;

use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OdontogramEntry>
 */
class OdontogramEntryFactory extends Factory
{
    protected $model = OdontogramEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'branch_id' => null,
            'appointment_id' => null,
            'service_id' => null,
            'dentist_id' => null,
            'assistant_id' => null,
            'scope' => OdontogramScope::Surface,
            'tooth_number' => 16,
            'bridge_to_tooth' => null,
            'arch' => null,
            'surfaces' => ['O'],
            'condition_code' => OdontogramCondition::Caries,
            'status' => OdontogramStatus::Finding,
            'notes' => null,
        ];
    }

    public function finding(): static
    {
        return $this->state(fn (): array => [
            'status' => OdontogramStatus::Finding,
        ]);
    }

    public function preExisting(): static
    {
        return $this->state(fn (): array => [
            'condition_code' => OdontogramCondition::Resin,
            'status' => OdontogramStatus::PreExisting,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (): array => [
            'condition_code' => OdontogramCondition::Resin,
            'status' => OdontogramStatus::Planned,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'condition_code' => OdontogramCondition::Resin,
            'status' => OdontogramStatus::Completed,
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (): array => [
            'status' => OdontogramStatus::Superseded,
        ]);
    }
}
