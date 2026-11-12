<?php

namespace App\Actions\Odontogram;

use App\Enums\OdontogramStatus;
use App\Models\OdontogramEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteOdontogramEntryAction
{
    public function __construct(
        protected SupersedeOverlappingFindingsAction $supersedeFindings,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function handle(OdontogramEntry $entry, array $attributes = []): OdontogramEntry
    {
        if ($entry->status !== OdontogramStatus::Planned) {
            throw ValidationException::withMessages([
                'status' => __('Only planned treatments can be marked as completed.'),
            ]);
        }

        return DB::transaction(function () use ($entry, $attributes): OdontogramEntry {
            $updatable = array_intersect_key($attributes, array_flip([
                'dentist_id',
                'assistant_id',
                'appointment_id',
                'branch_id',
                'notes',
            ]));

            $entry->update([
                ...$updatable,
                'status' => OdontogramStatus::Completed,
            ]);

            $this->supersedeFindings->handle($entry);

            return $entry->refresh();
        });
    }
}
