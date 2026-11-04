<?php

use App\Enums\CommissionType;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);
});

it('lists and searches services in the table', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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

it('creates a service with fixed commission and empty default price', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Consultation',
            'default_price' => null,
            'commission_type' => CommissionType::Fixed->value,
            'default_commission' => 50.00,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Service::class, [
        'name' => 'Consultation',
        'default_price' => null,
        'commission_type' => CommissionType::Fixed->value,
        'default_commission' => 50.00,
    ]);
});

it('requires default price when commission_type is percentage', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Specialist Review',
            'default_price' => null,
            'commission_type' => CommissionType::Percentage->value,
            'default_commission' => 15.00,
        ])
        ->call('create')
        ->assertHasFormErrors(['default_price' => 'required'])
        ->assertNotNotified();

    assertDatabaseCount(Service::class, 0);
});

it('rejects a percentage commission greater than 100', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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

it('soft deletes a service from the edit page and supports restore and force delete', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $service = Service::factory()->for($user)->create();

    // 1. Soft delete
    Livewire::test(EditService::class, ['record' => $service->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($service);
    expect($service->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListServices::class)
        ->assertCanNotSeeTableRecords([$service])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$service]);

    // 3. Restore
    Livewire::test(EditService::class, ['record' => $service->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($service->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $service->delete();
    Livewire::test(EditService::class, ['record' => $service->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($service);
});

it('only displays services owned by the authenticated user in the list table', function () {
    $user = User::factory()->superAdmin()->create();
    $otherUser = User::factory()->superAdmin()->create();

    $userService = Service::factory()->for($user)->create(['name' => 'My Cleaning']);
    $otherService = Service::factory()->for($otherUser)->create(['name' => 'Other Person Service']);

    $this->actingAs($user);

    Livewire::test(ListServices::class)
        ->assertCanSeeTableRecords([$userService])
        ->assertCanNotSeeTableRecords([$otherService]);
});

it('assigns user_id automatically to the authenticated user on create', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    Livewire::test(CreateService::class)
        ->fillForm([
            'name' => 'Fluoride Treatment',
            'default_price' => 80.00,
            'commission_type' => CommissionType::Fixed->value,
            'default_commission' => 20.00,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Service::class, [
        'name' => 'Fluoride Treatment',
        'user_id' => $user->id,
    ]);
});

it('forbids editing a service owned by another user with a 404 response', function () {
    $user = User::factory()->superAdmin()->create();
    $otherUser = User::factory()->superAdmin()->create();

    $otherService = Service::factory()->for($otherUser)->create();

    $this->actingAs($user);

    Livewire::test(EditService::class, ['record' => $otherService->id])
        ->assertNotFound();
});
