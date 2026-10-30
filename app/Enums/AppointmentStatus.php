<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Confirmed => __('Confirmed'),
            self::InProgress => __('In progress'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No show'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Confirmed => 'primary',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::NoShow => 'gray',
        };
    }

    public function occupiesTimeSlot(): bool
    {
        return match ($this) {
            self::Scheduled, self::Confirmed, self::InProgress, self::Completed => true,
            self::Cancelled, self::NoShow => false,
        };
    }
}
