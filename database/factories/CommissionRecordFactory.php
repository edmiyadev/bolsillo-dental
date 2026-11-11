<?php

namespace Database\Factories;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\CommissionRecord;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<CommissionRecord>
 */
class CommissionRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 50, 500);
        $type = fake()->randomElement(CommissionType::cases());
        $commission = $type === CommissionType::Percentage
            ? fake()->randomFloat(2, 5, 50)
            : fake()->randomFloat(2, 10, min(100, $price));

        return [
            'created_by' => fn (): mixed => Auth::id() ?? User::factory(),
            'service_id' => fn (array $attributes): mixed => Service::factory()->state([
                'created_by' => $attributes['created_by'],
            ]),
            'price' => $price,
            'commission' => $commission,
            'commission_type' => $type,
            'calculated_earnings' => 0.00,
            'status' => fake()->randomElement(CommissionStatus::cases()),
            'notes' => fake()->optional()->sentence(),
            'worked_at' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommissionStatus::Pending,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommissionStatus::Paid,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommissionStatus::Cancelled,
        ]);
    }

    public function fixedCommission(float $commission = 50.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'commission_type' => CommissionType::Fixed,
            'commission' => $commission,
        ]);
    }

    public function percentageCommission(float $commission = 20.00): static
    {
        return $this->state(fn (array $attributes): array => [
            'commission_type' => CommissionType::Percentage,
            'commission' => $commission,
        ]);
    }
}
