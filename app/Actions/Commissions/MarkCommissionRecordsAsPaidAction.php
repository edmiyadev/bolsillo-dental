<?php

namespace App\Actions\Commissions;

use App\Enums\CommissionStatus;
use App\Models\CommissionRecord;
use Illuminate\Support\Collection;

class MarkCommissionRecordsAsPaidAction
{
    /**
     * @param  CommissionRecord|Collection<int, CommissionRecord>  $records
     */
    public function handle(CommissionRecord|Collection $records): int
    {
        $ids = $records instanceof CommissionRecord
            ? [$records->id]
            : $records->pluck('id')->all();

        return CommissionRecord::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', CommissionStatus::Paid)
            ->update(['status' => CommissionStatus::Paid]);
    }
}
