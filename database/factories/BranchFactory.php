<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\Branch;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->company().' Sucursal',
            'code' => 'SUC-'.fake()->unique()->numerify('###'),
            'phone' => '809-555-'.fake()->numerify('####'),
            'address' => fake()->address(),
            'opening_hours' => DayOfWeek::defaultSchedule(),
            'is_main' => false,
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);
    }
}
