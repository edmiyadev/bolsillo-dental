<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OdontogramStatus: string implements HasColor, HasLabel
{
    case Finding = 'finding';
    case PreExisting = 'pre_existing';
    case Planned = 'planned';
    case Completed = 'completed';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Finding => __('Pathological finding'),
            self::PreExisting => __('Pre-existing'),
            self::Planned => __('Planned'),
            self::Completed => __('Completed'),
            self::Superseded => __('Superseded'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Finding, self::Planned => 'danger',
            self::PreExisting => 'success',
            self::Completed => 'info',
            self::Superseded, self::Cancelled => 'gray',
        };
    }

    public function hexColor(): string
    {
        return match ($this) {
            self::Finding, self::Planned => '#EF4444',
            self::PreExisting => '#10B981',
            self::Completed => '#3B82F6',
            self::Superseded, self::Cancelled => '#64748B',
        };
    }

    public function isActiveInCurrentView(): bool
    {
        return match ($this) {
            self::Finding, self::PreExisting, self::Planned, self::Completed => true,
            self::Superseded, self::Cancelled => false,
        };
    }

    public function isVisibleInInitialView(): bool
    {
        return match ($this) {
            self::Finding, self::PreExisting, self::Superseded => true,
            self::Planned, self::Completed, self::Cancelled => false,
        };
    }

    public function requiresService(): bool
    {
        return match ($this) {
            self::Planned, self::Completed => true,
            self::Finding, self::PreExisting, self::Superseded, self::Cancelled => false,
        };
    }
}
