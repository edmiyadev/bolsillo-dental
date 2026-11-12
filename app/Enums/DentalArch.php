<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DentalArch: string implements HasLabel
{
    case Upper = 'upper';
    case Lower = 'lower';
    case Full = 'full';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upper => __('Upper arch'),
            self::Lower => __('Lower arch'),
            self::Full => __('Full mouth'),
        };
    }
}
