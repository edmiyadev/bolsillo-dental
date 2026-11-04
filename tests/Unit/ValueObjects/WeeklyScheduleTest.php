<?php

use App\Enums\DayOfWeek;
use App\ValueObjects\WeeklySchedule;
use Tests\TestCase;

uses(TestCase::class);

it('creates a weekly schedule from array or json', function (): void {
    $raw = [
        ['day' => 'monday', 'is_open' => true, 'open_time' => '08:00', 'close_time' => '17:00'],
        ['day' => 'sunday', 'is_open' => false, 'open_time' => null, 'close_time' => null],
    ];

    $schedule = WeeklySchedule::from($raw);
    expect($schedule->all())->toHaveCount(2)
        ->and($schedule->workingDays())->toHaveCount(1)
        ->and($schedule->isOpenOn('monday'))->toBeTrue()
        ->and($schedule->isOpenOn('sunday'))->toBeFalse()
        ->and($schedule->intervalFor(DayOfWeek::Sunday))->toBeNull()
        ->and($schedule->intervalFor(DayOfWeek::Monday))->toEqual([
            'open_time' => '08:00',
            'close_time' => '17:00',
        ])
        ->and($schedule->workingDaysLabels())->toEqual([DayOfWeek::Monday->getLabel()])
        ->and($schedule->workingScheduleRows())->toEqual([
            [
                'day_label' => DayOfWeek::Monday->getLabel(),
                'open_time' => '08:00',
                'close_time' => '17:00',
            ],
        ]);

    $fromJson = WeeklySchedule::from(json_encode($raw));
    expect($fromJson->all())->toHaveCount(2)
        ->and($fromJson->workingDays())->toHaveCount(1);
});

it('handles empty or null values gracefully', function (): void {
    $schedule = WeeklySchedule::from(null);
    expect($schedule->all())->toBeEmpty()
        ->and($schedule->workingDays())->toBeEmpty()
        ->and($schedule->workingDaysLabels())->toBeEmpty()
        ->and($schedule->workingScheduleRows())->toBeEmpty()
        ->and($schedule->isOpenOn('monday'))->toBeFalse()
        ->and($schedule->intervalFor('monday'))->toBeNull();
});
