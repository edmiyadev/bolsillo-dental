<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DentalSpecialty: string implements HasColor, HasLabel
{
    case General = 'general';
    case Orthodontics = 'orthodontics';
    case Endodontics = 'endodontics';
    case PediatricDentistry = 'pediatric_dentistry';
    case Periodontics = 'periodontics';
    case OralSurgery = 'oral_surgery';
    case Prosthodontics = 'prosthodontics';
    case Implantology = 'implantology';
    case Aesthetics = 'aesthetics';

    public function getLabel(): string
    {
        return match ($this) {
            self::General => __('General Dentistry'),
            self::Orthodontics => __('Orthodontics'),
            self::Endodontics => __('Endodontics'),
            self::PediatricDentistry => __('Pediatric Dentistry'),
            self::Periodontics => __('Periodontics'),
            self::OralSurgery => __('Oral Surgery'),
            self::Prosthodontics => __('Prosthodontics'),
            self::Implantology => __('Implantology'),
            self::Aesthetics => __('Dental Aesthetics'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::General => 'info',
            self::Orthodontics => 'success',
            self::Endodontics => 'danger',
            self::PediatricDentistry => 'warning',
            self::Periodontics => 'warning',
            self::OralSurgery => 'danger',
            self::Prosthodontics => 'info',
            self::Implantology => 'primary',
            self::Aesthetics => 'purple',
        };
    }
}
