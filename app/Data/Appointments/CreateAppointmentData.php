<?php

namespace App\Data\Appointments;

use App\Enums\AppointmentStatus;
use Illuminate\Support\Carbon;

readonly class CreateAppointmentData
{
    public function __construct(
        public int $branchId,
        public int $patientId,
        public int $dentistId,
        public Carbon $startsAt,
        public int $durationMinutes = 30,
        public ?string $serviceId = null,
        public ?string $customReason = null,
        public AppointmentStatus $status = AppointmentStatus::Scheduled,
        public ?int $cubicleId = null,
        public ?string $observations = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $rawServiceId = isset($data['service_id']) && filled($data['service_id'])
            ? (string) $data['service_id']
            : null;

        $customReason = filled($data['custom_reason'] ?? null)
            ? trim((string) $data['custom_reason'])
            : null;

        $observations = filled($data['observations'] ?? null)
            ? trim((string) $data['observations'])
            : null;

        $durationMinutes = filled($data['duration_minutes'] ?? null)
            ? max(1, (int) $data['duration_minutes'])
            : 30;

        $startsAt = ($data['starts_at'] ?? null) instanceof Carbon
            ? $data['starts_at']
            : Carbon::parse((string) ($data['starts_at'] ?? 'now'));

        return new self(
            branchId: (int) ($data['branch_id'] ?? 0),
            patientId: (int) ($data['patient_id'] ?? 0),
            dentistId: (int) ($data['dentist_id'] ?? 0),
            startsAt: $startsAt,
            durationMinutes: $durationMinutes,
            serviceId: $rawServiceId,
            customReason: $customReason,
            status: AppointmentStatus::Scheduled,
            cubicleId: filled($data['cubicle_id'] ?? null) ? (int) $data['cubicle_id'] : null,
            observations: $observations,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'branch_id' => $this->branchId,
            'patient_id' => $this->patientId,
            'dentist_id' => $this->dentistId,
            'service_id' => $this->serviceId,
            'custom_reason' => $this->customReason,
            'starts_at' => $this->startsAt,
            'duration_minutes' => $this->durationMinutes,
            'status' => $this->status,
            'cubicle_id' => $this->cubicleId,
            'observations' => $this->observations,
        ];
    }
}
