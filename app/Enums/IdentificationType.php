<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IdentificationType: string implements HasColor, HasLabel
{
    case IdentityCard = 'identity card';
    case Passport = 'passport';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdentityCard => __('Identity card'),
            self::Passport => __('Passport'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IdentityCard => 'info',
            self::Passport => 'success',
        };
    }
}
