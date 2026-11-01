<?php

namespace App\Data\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Support\Carbon;

readonly class UpdateAppointmentData
{
    public function __construct(
        public ?int $branchId = null,
        public ?int $patientId = null,
        public ?int $dentistId = null,
        public ?Carbon $startsAt = null,
        public ?int $durationMinutes = null,
        public ?string $serviceId = null,
        public ?string $customReason = null,
        public ?AppointmentStatus $status = null,
        public ?int $cubicleId = null,
        public ?string $observations = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?Appointment $existing = null): self
    {
        $status = null;
        if (array_key_exists('status', $data) && filled($data['status'])) {
            $status = $data['status'] instanceof AppointmentStatus
                ? $data['status']
                : AppointmentStatus::tryFrom((string) $data['status']);
        } elseif ($existing?->status) {
            $status = $existing->status;
        }

        $startsAt = null;
        if (array_key_exists('starts_at', $data) && filled($data['starts_at'])) {
            $startsAt = $data['starts_at'] instanceof Carbon
                ? $data['starts_at']
                : Carbon::parse((string) $data['starts_at']);
        } elseif ($existing?->starts_at) {
            $startsAt = $existing->starts_at;
        }

        $durationMinutes = null;
        if (array_key_exists('duration_minutes', $data) && filled($data['duration_minutes'])) {
            $durationMinutes = max(1, (int) $data['duration_minutes']);
        } elseif ($existing?->duration_minutes) {
            $durationMinutes = $existing->duration_minutes;
        }

        $rawServiceId = null;
        if (array_key_exists('service_id', $data)) {
            $rawServiceId = filled($data['service_id']) ? (string) $data['service_id'] : null;
        } elseif ($existing) {
            $rawServiceId = $existing->is_custom_reason ? 'other' : ($existing->service_id ? (string) $existing->service_id : null);
        }

        $customReason = null;
        if (array_key_exists('custom_reason', $data)) {
            $customReason = filled($data['custom_reason']) ? trim((string) $data['custom_reason']) : null;
        } elseif ($existing?->custom_reason) {
            $customReason = $existing->custom_reason;
        }

        $observations = null;
        if (array_key_exists('observations', $data)) {
            $observations = filled($data['observations']) ? trim((string) $data['observations']) : null;
        } elseif ($existing?->observations) {
            $observations = $existing->observations;
        }

        return new self(
            branchId: array_key_exists('branch_id', $data)
                ? (filled($data['branch_id']) ? (int) $data['branch_id'] : null)
                : $existing?->branch_id,
            patientId: array_key_exists('patient_id', $data)
                ? (filled($data['patient_id']) ? (int) $data['patient_id'] : null)
                : $existing?->patient_id,
            dentistId: array_key_exists('dentist_id', $data)
                ? (filled($data['dentist_id']) ? (int) $data['dentist_id'] : null)
                : $existing?->dentist_id,
            startsAt: $startsAt,
            durationMinutes: $durationMinutes,
            serviceId: $rawServiceId,
            customReason: $customReason,
            status: $status ?? AppointmentStatus::Scheduled,
            cubicleId: array_key_exists('cubicle_id', $data)
                ? (filled($data['cubicle_id']) ? (int) $data['cubicle_id'] : null)
                : $existing?->cubicle_id,
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
