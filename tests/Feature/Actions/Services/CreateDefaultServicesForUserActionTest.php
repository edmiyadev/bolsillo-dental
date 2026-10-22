<?php

use App\Actions\Services\CreateDefaultServicesForUserAction;
use App\Enums\CommissionType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates default assistant services for a user', function () {
    $user = User::factory()->create();

    $action = app(CreateDefaultServicesForUserAction::class);
    $action->handle($user);

    expect($user->services()->count())->toBe(5);

    $this->assertDatabaseHas(Service::class, [
        'user_id' => $user->id,
        'name' => 'Asistencia general',
        'default_price' => null,
        'default_commission' => 250.00,
        'commission_type' => CommissionType::Fixed->value,
    ]);

    $this->assertDatabaseHas(Service::class, [
        'user_id' => $user->id,
        'name' => 'Limpieza bucal (Profilaxis)',
        'default_price' => 1500.00,
        'default_commission' => 20.00,
        'commission_type' => CommissionType::Percentage->value,
    ]);
});

it('does not duplicate services if user already has services', function () {
    $user = User::factory()->create();
    $action = app(CreateDefaultServicesForUserAction::class);

    $action->handle($user);
    expect($user->services()->count())->toBe(5);

    // Call again to verify idempotency
    $action->handle($user);
    expect($user->services()->count())->toBe(5);
});
