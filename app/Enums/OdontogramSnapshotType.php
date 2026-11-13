<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OdontogramSnapshotType: string implements HasColor, HasLabel
{
    case Initial = 'initial';
    case TreatmentCompleted = 'treatment_completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Initial => __('Initial Odontogram (Base Diagnosis)'),
            self::TreatmentCompleted => __('Completed Treatment Plan'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Initial => 'info',
            self::TreatmentCompleted => 'success',
        };
    }
}
