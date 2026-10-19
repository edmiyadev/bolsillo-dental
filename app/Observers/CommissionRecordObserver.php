<?php

namespace App\Observers;

use App\Actions\Commissions\CalculateCommissionEarningsAction;
use App\Enums\CommissionType;
use App\Models\CommissionRecord;

class CommissionRecordObserver
{
    public function __construct(
        private CalculateCommissionEarningsAction $calculateEarnings,
    ) {}

    public function saving(CommissionRecord $record): void
    {
        if ($record->commission === null || $record->commission_type === null) {
            return;
        }

        $type = $record->commission_type instanceof CommissionType
            ? $record->commission_type
            : CommissionType::from((string) $record->commission_type);

        if ($type === CommissionType::Percentage && $record->price === null) {
            return;
        }

        $record->calculated_earnings = $this->calculateEarnings->handle(
            $record->price !== null ? (float) $record->price : null,
            (float) $record->commission,
            $type,
        );
    }
}
