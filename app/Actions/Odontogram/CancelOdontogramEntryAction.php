<?php

namespace App\Actions\Odontogram;

use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use Illuminate\Validation\ValidationException;

class CancelOdontogramEntryAction
{
    /**
     * @throws ValidationException
     */
    public function handle(OdontogramEntry $entry): OdontogramEntry
    {
        if (in_array($entry->status, [OdontogramStatus::Superseded, OdontogramStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => __('This odontogram record is already archived or cancelled.'),
            ]);
        }

        $entry->update([
            'status' => OdontogramStatus::Cancelled,
        ]);

        return $entry->refresh();
    }
}
