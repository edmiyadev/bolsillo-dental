<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DayOfWeek: string implements HasLabel
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monday => __('Monday'),
            self::Tuesday => __('Tuesday'),
            self::Wednesday => __('Wednesday'),
            self::Thursday => __('Thursday'),
            self::Friday => __('Friday'),
            self::Saturday => __('Saturday'),
            self::Sunday => __('Sunday'),
        };
    }

    /**
     * @return array<int, array{day: string, is_open: bool, open_time: ?string, close_time: ?string}>
     */
    public static function defaultSchedule(): array
    {
        return array_map(
            fn (self $day): array => [
                'day' => $day->value,
                'is_open' => $day !== self::Sunday,
                'open_time' => $day !== self::Sunday ? '08:00' : null,
                'close_time' => match ($day) {
                    self::Sunday => null,
                    self::Saturday => '13:00',
                    default => '18:00',
                },
            ],
            self::cases(),
        );
    }
}
