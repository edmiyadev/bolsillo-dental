<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum NavigationGroup implements HasIcon, HasLabel
{
    case Settings;

    public function getLabel(): string
    {
        return match ($this) {
            self::Settings => __('Settings'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Settings => Heroicon::Cog6Tooth,
        };
    }
}
