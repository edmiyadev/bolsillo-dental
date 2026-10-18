<?php

use App\Enums\CommissionType;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;

uses(RefreshDatabase::class);

it('lists and searches services in the table', function () {
    $this->actingAs(User::factory()->create());

    $services = Service::factory()
        ->count(2)
        ->sequence(
            ['name' => 'Dental Cleaning'],
            ['name' => 'Simple Extraction'],
        )
        ->create();

    Livewire::test(ListServices::class)
        ->assertOk()
        ->assertCanSeeTableRecords($services)
        ->searchTable($services->first()->name)
        ->assertCanSeeTableRecords($services->take(1))
        ->assertCanNotSeeTableRecords($services->skip(1));
});

it('creates a service with a percentage commission', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Dental Cleaning',
            'default_price' => 150.00,
            'commission_type' => CommissionType::Percentage->value,
            'default_commission' => 20.00,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Service::class, [
        'name' => 'Dental Cleaning',
        'default_price' => 150.00,
        'commission_type' => CommissionType::Percentage->value,
        'default_commission' => 20.00,
    ]);
});

it('creates a service with a fixed commission greater than 100', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Dental Implant',
            'default_price' => 1200.00,
            'commission_type' => CommissionType::Fixed->value,
            'default_commission' => 250.00,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Service::class, [
        'name' => 'Dental Implant',
        'default_price' => 1200.00,
        'commission_type' => CommissionType::Fixed->value,
        'default_commission' => 250.00,
    ]);
});

it('rejects a percentage commission greater than 100', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Teeth Whitening',
            'default_price' => 300.00,
            'commission_type' => CommissionType::Percentage->value,
            'default_commission' => 120.00,
        ])
        ->call('create')
        ->assertHasFormErrors(['default_commission' => 'max'])
        ->assertNotNotified();

    assertDatabaseCount(Service::class, 0);
});

it('updates an existing service', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->percentageCommission(15.00)->create([
        'name' => 'Orthodontics',
        'default_price' => 500.00,
    ]);

    Livewire::test(EditService::class, ['record' => $service->id])
        ->fillForm([
            'name' => 'Clear Aligners',
            'default_price' => 750.00,
            'commission_type' => CommissionType::Fixed->value,
            'default_commission' => 150.00,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Service::class, [
        'id' => $service->id,
        'name' => 'Clear Aligners',
        'default_price' => 750.00,
        'commission_type' => CommissionType::Fixed->value,
        'default_commission' => 150.00,
    ]);
});

it('deletes a service from the edit page', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create();

    Livewire::test(EditService::class, ['record' => $service->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertModelMissing($service);
});
