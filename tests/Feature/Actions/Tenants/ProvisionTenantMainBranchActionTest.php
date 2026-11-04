<?php

use App\Actions\Tenants\ProvisionTenantMainBranchAction;
use App\Enums\DayOfWeek;
use App\Models\Branch;
use App\Models\Clinic;
use App\ValueObjects\WeeklySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions the main branch for a clinic with default values', function (): void {
    $clinic = Clinic::factory()->create([
        'name' => 'Clínica Dental Sonrisa',
        'phone' => '809-555-1234',
        'address' => 'Av. Winston Churchill #10',
    ]);

    $action = app(ProvisionTenantMainBranchAction::class);
    $branch = $action->handle($clinic);

    expect($branch)->toBeInstanceOf(Branch::class)
        ->and($branch->name)->toBe('Sede Principal')
        ->and($branch->code)->toBe('MAIN')
        ->and($branch->phone)->toBe('809-555-1234')
        ->and($branch->address)->toBe('Av. Winston Churchill #10')
        ->and($branch->is_main)->toBeTrue()
        ->and($branch->is_active)->toBeTrue()
        ->and($branch->clinic_id)->toBe($clinic->id)
        ->and($branch->opening_hours)->toBeInstanceOf(WeeklySchedule::class)
        ->and($branch->opening_hours->toArray())->toEqual(DayOfWeek::defaultSchedule());

    // Calling again does not duplicate
    $action->handle($clinic);
    expect($clinic->branches()->count())->toBe(1);
});
