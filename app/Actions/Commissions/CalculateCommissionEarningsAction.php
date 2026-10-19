<?php

namespace App\Actions\Commissions;

use App\Enums\CommissionType;

class CalculateCommissionEarningsAction
{
    public function handle(?float $price, float $commission, CommissionType $type): float
    {
        return match ($type) {
            CommissionType::Fixed => round($commission, 2),
            CommissionType::Percentage => round(($price ?? 0.0) * ($commission / 100), 2),
        };
    }
}
