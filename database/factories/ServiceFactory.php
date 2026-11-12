<?php

namespace Database\Factories;

use App\Enums\CommissionType;
use App\Enums\DentalSpecialty;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => fn (): mixed => Auth::id() ?? User::factory(),
            'name' => fake()->words(3, true),
            'default_price' => fake()->randomFloat(2, 20, 500),
            'default_commission' => fake()->randomFloat(2, 5, 50),
            'commission_type' => fake()->randomElement(CommissionType::cases()),
            'specialty' => DentalSpecialty::General,
            'duration_minutes' => 30,
        ];
    }

    public function fixedCommission(float $commission = 25.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'commission_type' => CommissionType::Fixed,
            'default_commission' => $commission,
        ]);
    }

    public function percentageCommission(float $commission = 15.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'commission_type' => CommissionType::Percentage,
            'default_commission' => $commission,
        ]);
    }
}
