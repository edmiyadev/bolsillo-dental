<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoleEnum: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Dentist = 'dentist';
    case Receptionist = 'receptionist';
    case DentalAssistant = 'dental_assistant';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Administrator'),
            self::Dentist => __('Dentist'),
            self::Receptionist => __('Receptionist'),
            self::DentalAssistant => __('Dental Assistant'),
        };
    }
}
