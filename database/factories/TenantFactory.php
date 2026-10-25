<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Clínica '.fake()->unique()->company();
        $slug = Str::slug($name);

        return [
            'id' => $slug,
            'name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'is_active' => true,
            'tenancy_db_name' => 'bolsillo_tenant_'.Str::replace('-', '_', $slug),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
