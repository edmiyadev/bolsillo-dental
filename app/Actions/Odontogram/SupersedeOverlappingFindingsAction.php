<?php

namespace App\Actions\Odontogram;

use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use App\Support\OdontogramGeometryCatalog;

class SupersedeOverlappingFindingsAction
{
    /**
     * Transition prior active findings covered by a newly completed treatment to superseded.
     */
    public function handle(OdontogramEntry $completedEntry): void
    {
        if ($completedEntry->status !== OdontogramStatus::Completed || $completedEntry->tooth_number === null) {
            return;
        }

        if (in_array($completedEntry->condition_code, [OdontogramCondition::Crown, OdontogramCondition::Extraction], true)) {
            OdontogramEntry::query()
                ->where('patient_id', $completedEntry->patient_id)
                ->where('tooth_number', $completedEntry->tooth_number)
                ->where('status', OdontogramStatus::Finding)
                ->where('id', '!=', $completedEntry->id)
                ->update(['status' => OdontogramStatus::Superseded->value]);

            return;
        }

        if ($completedEntry->scope !== OdontogramScope::Surface || empty($completedEntry->surfaces)) {
            return;
        }

        $findings = OdontogramEntry::query()
            ->where('patient_id', $completedEntry->patient_id)
            ->where('tooth_number', $completedEntry->tooth_number)
            ->where('scope', OdontogramScope::Surface)
            ->where('status', OdontogramStatus::Finding)
            ->where('id', '!=', $completedEntry->id)
            ->get();

        foreach ($findings as $finding) {
            if (OdontogramGeometryCatalog::surfacesOverlap($finding->surfaces ?? [], $completedEntry->surfaces)) {
                $finding->update(['status' => OdontogramStatus::Superseded]);
            }
        }
    }
}
