<?php

namespace App\Actions\Odontogram;

use App\Enums\OdontogramSnapshotType;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use App\Models\OdontogramSnapshot;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveOdontogramSessionAction
{
    public function __construct(
        protected RecordOdontogramEntryAction $recordEntry,
        protected CompleteOdontogramEntryAction $completeEntry,
        protected CancelOdontogramEntryAction $cancelEntry,
        protected CreateOdontogramSnapshotAction $createSnapshot,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $pendingAdditions
     * @param  array<int, int>  $pendingCompletions
     * @param  array<int, int>  $pendingCancellations
     * @return array{snapshot: OdontogramSnapshot|null}
     *
     * @throws ValidationException
     */
    public function handle(
        Patient $patient,
        array $pendingAdditions = [],
        array $pendingCompletions = [],
        array $pendingCancellations = [],
        bool $clearCanvas = false,
        bool $asInitialSnapshot = false,
        ?string $snapshotNotes = null,
    ): array {
        return DB::transaction(function () use (
            $patient,
            $pendingAdditions,
            $pendingCompletions,
            $pendingCancellations,
            $clearCanvas,
            $asInitialSnapshot,
            $snapshotNotes,
        ): array {
            $hadInitialSnapshot = $patient->odontogramSnapshots()
                ->where('type', OdontogramSnapshotType::Initial)
                ->exists();

            if ($clearCanvas) {
                OdontogramEntry::query()
                    ->forPatient($patient->id)
                    ->activeInCurrentView()
                    ->update(['status' => OdontogramStatus::Superseded->value]);
            } else {
                foreach ($pendingCancellations as $entryId) {
                    $entry = OdontogramEntry::query()
                        ->forPatient($patient->id)
                        ->find($entryId);

                    if ($entry instanceof OdontogramEntry) {
                        $this->cancelEntry->handle($entry);
                    }
                }

                foreach ($pendingCompletions as $entryId) {
                    $entry = OdontogramEntry::query()
                        ->forPatient($patient->id)
                        ->find($entryId);

                    if ($entry instanceof OdontogramEntry) {
                        $this->completeEntry->handle($entry);
                    }
                }
            }

            foreach ($pendingAdditions as $payload) {
                $this->recordEntry->handle([
                    ...$payload,
                    'patient_id' => $patient->id,
                ]);
            }

            $snapshot = null;

            if (! $hadInitialSnapshot || $asInitialSnapshot) {
                $snapshot = $this->createSnapshot->handle(
                    patient: $patient,
                    type: OdontogramSnapshotType::Initial,
                    notes: $snapshotNotes,
                );
            }

            return [
                'snapshot' => $snapshot,
            ];
        });
    }
}
