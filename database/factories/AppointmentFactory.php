<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'patient_id' => Patient::factory(),
            'dentist_id' => User::factory(),
            'cubicle_id' => fn (array $attributes): int => Cubicle::factory()->create([
                'branch_id' => $attributes['branch_id'],
            ])->id,
            'service_id' => fn (array $attributes): int => Service::factory()->create([
                'created_by' => $attributes['dentist_id'],
            ])->id,
            'is_custom_reason' => false,
            'custom_reason' => null,
            'starts_at' => Carbon::parse('2026-11-02 09:00:00'),
            'duration_minutes' => 30,
            'ends_at' => fn (array $attributes): Carbon => Carbon::parse($attributes['starts_at'])
                ->copy()
                ->addMinutes((int) ($attributes['duration_minutes'] ?? 30)),
            'status' => AppointmentStatus::Scheduled,
            'observations' => null,
        ];
    }
}
