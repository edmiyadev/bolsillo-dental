<?php

namespace App\Support;

use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use Illuminate\Support\Collection;

class OdontogramStateResolver
{
    /**
     * Build the visual layer projection for all FDI teeth, bridges, and arch/mouth entries.
     *
     * @param  Collection<int, OdontogramEntry>  $entries
     * @return array{
     *     teeth: array<int, array<string, mixed>>,
     *     bridges: array<int, array<string, mixed>>,
     *     archEntries: Collection<int, OdontogramEntry>,
     * }
     */
    public function resolve(Collection $entries, string $timeline = 'current'): array
    {
        $filtered = $entries
            ->filter(function (OdontogramEntry $entry) use ($timeline): bool {
                return $timeline === 'initial'
                    ? $entry->status->isVisibleInInitialView()
                    : $entry->status->isActiveInCurrentView();
            })
            ->sortBy('id')
            ->values();

        $teeth = [];

        foreach (OdontogramGeometryCatalog::validToothNumbers() as $toothNumber) {
            $teeth[$toothNumber] = $this->resolveToothState(
                $toothNumber,
                $filtered->where('tooth_number', $toothNumber)->values(),
                $timeline,
            );
        }

        $bridges = $filtered
            ->filter(fn (OdontogramEntry $entry): bool => $entry->scope === OdontogramScope::Range
                && $entry->tooth_number !== null
                && $entry->bridge_to_tooth !== null)
            ->map(function (OdontogramEntry $entry) use ($timeline): array {
                $fromTooth = (int) $entry->tooth_number;

                return [
                    'entryId' => $entry->id,
                    'fromTooth' => $fromTooth,
                    'toTooth' => (int) $entry->bridge_to_tooth,
                    'isUpper' => OdontogramGeometryCatalog::isUpper($fromTooth),
                    'isPrimary' => OdontogramGeometryCatalog::isPrimary($fromTooth),
                    'color' => $this->resolveColor($entry, $timeline),
                    'status' => $entry->status,
                ];
            })
            ->values()
            ->all();

        $archEntries = $filtered
            ->filter(fn (OdontogramEntry $entry): bool => in_array($entry->scope, [OdontogramScope::Arch, OdontogramScope::Mouth], true))
            ->values();

        return [
            'teeth' => $teeth,
            'bridges' => $bridges,
            'archEntries' => $archEntries,
        ];
    }

    /**
     * @param  Collection<int, OdontogramEntry>  $toothEntries
     * @return array<string, mixed>
     */
    protected function resolveToothState(int $toothNumber, Collection $toothEntries, string $timeline): array
    {
        $mapping = OdontogramGeometryCatalog::surfaceMapping($toothNumber);
        $surfaceEntries = $toothEntries->where('scope', OdontogramScope::Surface)->values();

        $polygons = [];

        foreach ($mapping as $position => $surfaceCode) {
            $latestEntry = $surfaceEntries
                ->filter(fn (OdontogramEntry $entry): bool => in_array($surfaceCode, $entry->surfaces ?? [], true))
                ->last();

            $polygons[$position] = [
                'surface' => $surfaceCode,
                'color' => $latestEntry ? $this->resolveColor($latestEntry, $timeline) : '#FFFFFF',
                'condition' => $latestEntry?->condition_code,
                'status' => $latestEntry?->status,
                'entryId' => $latestEntry?->id,
                'isSealant' => $latestEntry?->condition_code === OdontogramCondition::Sealant,
                'isFracture' => $latestEntry?->condition_code === OdontogramCondition::Fracture,
            ];
        }

        $crownEntry = $this->latestConditionEntry($toothEntries, OdontogramCondition::Crown);
        $endodonticsEntry = $this->latestConditionEntry($toothEntries, OdontogramCondition::Endodontics);
        $implantEntry = $this->latestConditionEntry($toothEntries, OdontogramCondition::Implant);
        $extractionEntry = $this->latestConditionEntry($toothEntries, OdontogramCondition::Extraction);
        $missingEntry = $this->latestConditionEntry($toothEntries, OdontogramCondition::Missing);
        $toothFractureEntry = $toothEntries
            ->where('scope', OdontogramScope::Tooth)
            ->where('condition_code', OdontogramCondition::Fracture)
            ->last();

        $isCompletedExtraction = $extractionEntry?->status === OdontogramStatus::Completed;
        $isMissing = $missingEntry !== null;

        return [
            'toothNumber' => $toothNumber,
            'morphology' => OdontogramGeometryCatalog::morphology($toothNumber),
            'isUpper' => OdontogramGeometryCatalog::isUpper($toothNumber),
            'mapping' => $mapping,
            'polygons' => $polygons,
            'hasCrown' => $crownEntry !== null,
            'crown' => $crownEntry ? $this->overlayPayload($crownEntry, $timeline) : null,
            'hasEndodontics' => $endodonticsEntry !== null,
            'endodontics' => $endodonticsEntry ? $this->overlayPayload($endodonticsEntry, $timeline) : null,
            'hasImplant' => $implantEntry !== null,
            'implant' => $implantEntry ? $this->overlayPayload($implantEntry, $timeline) : null,
            'hasExtraction' => $extractionEntry !== null,
            'extraction' => $extractionEntry ? $this->overlayPayload($extractionEntry, $timeline) : null,
            'isMissing' => $isMissing,
            'missing' => $missingEntry ? $this->overlayPayload($missingEntry, $timeline) : null,
            'hasFracture' => $toothFractureEntry !== null,
            'fracture' => $toothFractureEntry ? $this->overlayPayload($toothFractureEntry, $timeline) : null,
            'isBlocked' => $isMissing || $isCompletedExtraction,
            'summaryLabel' => $this->buildSummaryLabel($toothEntries),
        ];
    }

    /**
     * @param  Collection<int, OdontogramEntry>  $toothEntries
     */
    protected function latestConditionEntry(Collection $toothEntries, OdontogramCondition $condition): ?OdontogramEntry
    {
        return $toothEntries
            ->where('condition_code', $condition)
            ->last();
    }

    /**
     * @return array{entryId: int, color: string, status: OdontogramStatus}
     */
    protected function overlayPayload(OdontogramEntry $entry, string $timeline): array
    {
        return [
            'entryId' => $entry->id,
            'color' => $this->resolveColor($entry, $timeline),
            'status' => $entry->status,
        ];
    }

    protected function resolveColor(OdontogramEntry $entry, string $timeline): string
    {
        if ($timeline === 'initial' && $entry->status === OdontogramStatus::Superseded) {
            return OdontogramStatus::Finding->hexColor();
        }

        return $entry->status->hexColor();
    }

    /**
     * @param  Collection<int, OdontogramEntry>  $toothEntries
     */
    protected function buildSummaryLabel(Collection $toothEntries): ?string
    {
        $latest = $toothEntries->last();

        if (! $latest instanceof OdontogramEntry) {
            return null;
        }

        $label = $latest->condition_code->getLabel();

        if ($latest->scope === OdontogramScope::Surface && ! empty($latest->surfaces)) {
            return "{$label} (".implode(',', $latest->surfaces).')';
        }

        return $label;
    }
}
