<?php

use App\Enums\DayOfWeek;
use App\Models\Branch;
use App\Models\Clinic;
use App\Rules\CollaboratorBranchSchedulesRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('passes validation when schedules are valid and do not overlap', function (): void {
    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $rule = new CollaboratorBranchSchedulesRule;
    $failed = false;

    $rule->validate('branchSchedules', [
        [
            'branch_id' => $mainBranch->id,
            'is_active' => true,
            'schedule' => [
                ['day' => 'monday', 'is_open' => true, 'open_time' => '08:00', 'close_time' => '12:00'],
            ],
        ],
    ], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

it('fails validation when same branch is assigned multiple times', function (): void {
    $clinic = Clinic::factory()->create();
    $branch = Branch::factory()->create(['clinic_id' => $clinic->id]);

    $rule = new CollaboratorBranchSchedulesRule;
    $errorMessage = null;

    $rule->validate('branchSchedules', [
        ['branch_id' => $branch->id, 'schedule' => []],
        ['branch_id' => $branch->id, 'schedule' => []],
    ], function (string $message) use (&$errorMessage): void {
        $errorMessage = $message;
    });

    expect($errorMessage)->not->toBeNull();
});

it('normalizes single-digit hours and compares intervals accurately', function (): void {
    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $rule = new CollaboratorBranchSchedulesRule;
    $failed = false;

    $rule->validate('branchSchedules', [
        [
            'branch_id' => $mainBranch->id,
            'is_active' => true,
            'schedule' => [
                ['day' => 'monday', 'is_open' => true, 'open_time' => '8:00', 'close_time' => '12:00'],
            ],
        ],
    ], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
