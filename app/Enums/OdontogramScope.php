<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OdontogramScope: string implements HasLabel
{
    case Surface = 'surface';
    case Tooth = 'tooth';
    case Range = 'range';
    case Arch = 'arch';
    case Mouth = 'mouth';

    public function getLabel(): string
    {
        return match ($this) {
            self::Surface => __('Surface'),
            self::Tooth => __('Full tooth'),
            self::Range => __('Range / Bridge'),
            self::Arch => __('Arch'),
            self::Mouth => __('Full mouth'),
        };
    }
}
