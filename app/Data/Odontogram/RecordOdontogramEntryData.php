<?php

namespace App\Data\Odontogram;

use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;

readonly class RecordOdontogramEntryData
{
    /**
     * @param  array<int, string>|null  $surfaces
     */
    public function __construct(
        public int $patientId,
        public OdontogramCondition $conditionCode,
        public OdontogramStatus $status,
        public OdontogramScope $scope,
        public ?int $toothNumber = null,
        public ?int $bridgeToTooth = null,
        public ?DentalArch $arch = null,
        public ?array $surfaces = null,
        public ?int $serviceId = null,
        public ?int $branchId = null,
        public ?int $appointmentId = null,
        public ?int $dentistId = null,
        public ?int $assistantId = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $condition = $data['condition_code'] instanceof OdontogramCondition
            ? $data['condition_code']
            : OdontogramCondition::from((string) $data['condition_code']);

        $status = $data['status'] instanceof OdontogramStatus
            ? $data['status']
            : OdontogramStatus::from((string) $data['status']);

        $scope = isset($data['scope']) && filled($data['scope'])
            ? ($data['scope'] instanceof OdontogramScope
                ? $data['scope']
                : OdontogramScope::from((string) $data['scope']))
            : $condition->defaultScope();

        $arch = isset($data['arch']) && filled($data['arch'])
            ? ($data['arch'] instanceof DentalArch
                ? $data['arch']
                : DentalArch::from((string) $data['arch']))
            : null;

        $surfaces = isset($data['surfaces']) && is_array($data['surfaces'])
            ? array_values(array_map(static fn (mixed $surface): string => (string) $surface, $data['surfaces']))
            : null;

        return new self(
            patientId: (int) ($data['patient_id'] ?? 0),
            conditionCode: $condition,
            status: $status,
            scope: $scope,
            toothNumber: filled($data['tooth_number'] ?? null) ? (int) $data['tooth_number'] : null,
            bridgeToTooth: filled($data['bridge_to_tooth'] ?? null) ? (int) $data['bridge_to_tooth'] : null,
            arch: $arch,
            surfaces: $surfaces,
            serviceId: filled($data['service_id'] ?? null) ? (int) $data['service_id'] : null,
            branchId: filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null,
            appointmentId: filled($data['appointment_id'] ?? null) ? (int) $data['appointment_id'] : null,
            dentistId: filled($data['dentist_id'] ?? null) ? (int) $data['dentist_id'] : null,
            assistantId: filled($data['assistant_id'] ?? null) ? (int) $data['assistant_id'] : null,
            notes: filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        );
    }
}
