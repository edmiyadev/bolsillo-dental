<?php

namespace App\Actions\Odontogram;

use App\Enums\OdontogramSnapshotType;
use App\Models\OdontogramEntry;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;

class CreateOdontogramSnapshotAction
{
    public function handle(
        Patient $patient,
        OdontogramSnapshotType $type = OdontogramSnapshotType::Initial,
        ?string $title = null,
        ?string $notes = null,
    ): OdontogramSnapshot {
        $activeEntries = OdontogramEntry::query()
            ->forPatient($patient->id)
            ->activeInCurrentView()
            ->with(['service', 'dentist', 'assistant', 'branch', 'appointment'])
            ->orderBy('id')
            ->get();

        $serializedEntries = $activeEntries->map(static fn (OdontogramEntry $entry): array => [
            'id' => $entry->id,
            'scope' => $entry->scope->value,
            'tooth_number' => $entry->tooth_number,
            'bridge_to_tooth' => $entry->bridge_to_tooth,
            'arch' => $entry->arch?->value,
            'surfaces' => $entry->surfaces,
            'condition_code' => $entry->condition_code->value,
            'status' => $entry->status->value,
            'service_id' => $entry->service_id,
            'service_name' => $entry->service?->name,
            'dentist_id' => $entry->dentist_id,
            'dentist_name' => $entry->dentist?->name,
            'assistant_id' => $entry->assistant_id,
            'assistant_name' => $entry->assistant?->name,
            'branch_id' => $entry->branch_id,
            'branch_name' => $entry->branch?->name,
            'appointment_id' => $entry->appointment_id,
            'notes' => $entry->notes,
            'created_at' => $entry->created_at?->format('d/m/Y H:i'),
        ])->values()->all();

        $resolvedTitle = $title ?? $this->defaultTitle($patient, $type);

        return OdontogramSnapshot::create([
            'patient_id' => $patient->id,
            'type' => $type,
            'title' => $resolvedTitle,
            'notes' => $notes,
            'payload' => [
                'entries' => $serializedEntries,
            ],
        ]);
    }

    protected function defaultTitle(Patient $patient, OdontogramSnapshotType $type): string
    {
        $existingCount = OdontogramSnapshot::query()
            ->forPatient($patient->id)
            ->where('type', $type)
            ->count();

        if ($type === OdontogramSnapshotType::Initial) {
            return $existingCount === 0
                ? __('Initial Odontogram')
                : __('Initial Odontogram #:number', ['number' => $existingCount + 1]);
        }

        return __('Completed Treatments #:number', ['number' => $existingCount + 1]);
    }
}
