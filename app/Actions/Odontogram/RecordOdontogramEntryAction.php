<?php

namespace App\Actions\Odontogram;

use App\Data\Odontogram\RecordOdontogramEntryData;
use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use App\Support\OdontogramGeometryCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordOdontogramEntryAction
{
    public function __construct(
        protected SupersedeOverlappingFindingsAction $supersedeFindings,
    ) {}

    /**
     * Validate and normalize a draft odontogram entry without persisting it.
     *
     * @param  RecordOdontogramEntryData|array<string, mixed>  $data
     * @param  Collection<int, OdontogramEntry>|null  $effectiveActiveEntries
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateDraft(RecordOdontogramEntryData|array $data, ?Collection $effectiveActiveEntries = null): array
    {
        $dto = $data instanceof RecordOdontogramEntryData
            ? $data
            : RecordOdontogramEntryData::fromArray($data);

        $normalizedSurfaces = $this->validateAndNormalize($dto, $effectiveActiveEntries);

        return [
            'patient_id' => $dto->patientId,
            'branch_id' => $dto->branchId,
            'appointment_id' => $dto->appointmentId,
            'service_id' => $dto->serviceId,
            'dentist_id' => $dto->dentistId,
            'assistant_id' => $dto->assistantId,
            'scope' => $dto->scope->value,
            'tooth_number' => $dto->toothNumber,
            'bridge_to_tooth' => $dto->bridgeToTooth,
            'arch' => $dto->arch?->value,
            'surfaces' => $normalizedSurfaces,
            'condition_code' => $dto->conditionCode->value,
            'status' => $dto->status->value,
            'notes' => $dto->notes,
        ];
    }

    /**
     * @param  RecordOdontogramEntryData|array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(RecordOdontogramEntryData|array $data): OdontogramEntry
    {
        $dto = $data instanceof RecordOdontogramEntryData
            ? $data
            : RecordOdontogramEntryData::fromArray($data);

        $normalizedSurfaces = $this->validateAndNormalize($dto);

        return DB::transaction(function () use ($dto, $normalizedSurfaces): OdontogramEntry {
            $entry = OdontogramEntry::create([
                'patient_id' => $dto->patientId,
                'branch_id' => $dto->branchId,
                'appointment_id' => $dto->appointmentId,
                'service_id' => $dto->serviceId,
                'dentist_id' => $dto->dentistId,
                'assistant_id' => $dto->assistantId,
                'scope' => $dto->scope,
                'tooth_number' => $dto->toothNumber,
                'bridge_to_tooth' => $dto->bridgeToTooth,
                'arch' => $dto->arch,
                'surfaces' => $normalizedSurfaces,
                'condition_code' => $dto->conditionCode,
                'status' => $dto->status,
                'notes' => $dto->notes,
            ]);

            if ($entry->status === OdontogramStatus::Completed) {
                $this->supersedeFindings->handle($entry);
            }

            return $entry;
        });
    }

    /**
     * @param  Collection<int, OdontogramEntry>|null  $effectiveActiveEntries
     * @return array<int, string>|null
     *
     * @throws ValidationException
     */
    protected function validateAndNormalize(RecordOdontogramEntryData $dto, ?Collection $effectiveActiveEntries = null): ?array
    {
        if (! $dto->conditionCode->supportsScope($dto->scope)) {
            throw ValidationException::withMessages([
                'scope' => __('The selected scope is not valid for this clinical condition.'),
            ]);
        }

        if ($dto->status->requiresService() && $dto->serviceId === null) {
            throw ValidationException::withMessages([
                'service_id' => __('A service from the catalog is required for planned or completed treatments.'),
            ]);
        }

        if (in_array($dto->scope, [OdontogramScope::Arch, OdontogramScope::Mouth], true)) {
            if (! $dto->arch instanceof DentalArch) {
                throw ValidationException::withMessages([
                    'arch' => __('You must select an arch or full mouth for this procedure.'),
                ]);
            }

            return null;
        }

        if ($dto->toothNumber === null || ! OdontogramGeometryCatalog::isValidTooth($dto->toothNumber)) {
            throw ValidationException::withMessages([
                'tooth_number' => __('You must specify a valid FDI tooth number.'),
            ]);
        }

        $this->ensureToothIsNotBlocked($dto, $effectiveActiveEntries);

        if ($dto->scope === OdontogramScope::Range) {
            $this->validateBridgeRange($dto);

            return null;
        }

        if ($dto->scope === OdontogramScope::Tooth) {
            return null;
        }

        $normalized = OdontogramGeometryCatalog::normalizeSurfaces($dto->toothNumber, $dto->surfaces ?? []);

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'surfaces' => __('You must select at least one valid anatomical surface for this tooth.'),
            ]);
        }

        if ($dto->conditionCode === OdontogramCondition::Sealant) {
            if (! OdontogramGeometryCatalog::isPosterior($dto->toothNumber) || $normalized !== ['O']) {
                throw ValidationException::withMessages([
                    'condition_code' => __('Sealants can only be applied to the occlusal (O) surface of posterior teeth.'),
                ]);
            }
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, OdontogramEntry>|null  $effectiveActiveEntries
     *
     * @throws ValidationException
     */
    protected function ensureToothIsNotBlocked(RecordOdontogramEntryData $dto, ?Collection $effectiveActiveEntries = null): void
    {
        if (in_array($dto->conditionCode, [OdontogramCondition::Implant, OdontogramCondition::Bridge], true)) {
            return;
        }

        if ($effectiveActiveEntries !== null) {
            $isBlocked = $effectiveActiveEntries->contains(function (OdontogramEntry $entry) use ($dto): bool {
                if ($entry->tooth_number !== $dto->toothNumber) {
                    return false;
                }

                if ($entry->condition_code === OdontogramCondition::Missing
                    && in_array($entry->status, [OdontogramStatus::PreExisting, OdontogramStatus::Finding], true)) {
                    return true;
                }

                return $entry->condition_code === OdontogramCondition::Extraction
                    && $entry->status === OdontogramStatus::Completed;
            });
        } else {
            $isBlocked = OdontogramEntry::query()
                ->where('patient_id', $dto->patientId)
                ->where('tooth_number', $dto->toothNumber)
                ->where(function ($query): void {
                    $query->where(function ($inner): void {
                        $inner->where('condition_code', OdontogramCondition::Missing)
                            ->whereIn('status', [OdontogramStatus::PreExisting->value, OdontogramStatus::Finding->value]);
                    })->orWhere(function ($inner): void {
                        $inner->where('condition_code', OdontogramCondition::Extraction)
                            ->where('status', OdontogramStatus::Completed->value);
                    });
                })
                ->exists();
        }

        if ($isBlocked) {
            throw ValidationException::withMessages([
                'tooth_number' => __('This tooth is missing or extracted and only allows implant or bridge planning.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function validateBridgeRange(RecordOdontogramEntryData $dto): void
    {
        if ($dto->bridgeToTooth === null || ! OdontogramGeometryCatalog::isValidTooth($dto->bridgeToTooth)) {
            throw ValidationException::withMessages([
                'bridge_to_tooth' => __('You must specify a valid ending tooth for the bridge.'),
            ]);
        }

        if ($dto->toothNumber === $dto->bridgeToTooth) {
            throw ValidationException::withMessages([
                'bridge_to_tooth' => __('A bridge must connect two different teeth.'),
            ]);
        }

        if (! OdontogramGeometryCatalog::sameArch((int) $dto->toothNumber, $dto->bridgeToTooth)) {
            throw ValidationException::withMessages([
                'bridge_to_tooth' => __('Both bridge abutment teeth must belong to the same dental arch.'),
            ]);
        }
    }
}
