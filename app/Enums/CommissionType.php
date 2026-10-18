<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CommissionType: string implements HasColor, HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Percentage => 'Percentage',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fixed => 'info',
            self::Percentage => 'success',
        };
    }
}
