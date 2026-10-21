<?php

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Filament\Resources\CommissionRecords\Pages\CreateCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\EditCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\ListCommissionRecords;
use App\Models\CommissionRecord;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;

uses(RefreshDatabase::class);

it('lists and searches commission records in the table', function () {
    $this->actingAs(User::factory()->create());

    $serviceA = Service::factory()->create(['name' => 'Root Canal']);
    $serviceB = Service::factory()->create(['name' => 'Teeth Cleaning']);

    $recordA = CommissionRecord::factory()->create(['service_id' => $serviceA->id]);
    $recordB = CommissionRecord::factory()->create(['service_id' => $serviceB->id]);

    Livewire::test(ListCommissionRecords::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$recordA, $recordB])
        ->searchTable('Root Canal')
        ->assertCanSeeTableRecords([$recordA])
        ->assertCanNotSeeTableRecords([$recordB]);
});

it('filters commission records by status', function () {
    $this->actingAs(User::factory()->create());

    $pending = CommissionRecord::factory()->pending()->create();
    $paid = CommissionRecord::factory()->paid()->create();

    Livewire::test(ListCommissionRecords::class)
        ->assertCanSeeTableRecords([$pending, $paid])
        ->filterTable('status', CommissionStatus::Pending->value)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$paid]);
});

it('filters commission records by worked_at date range', function () {
    $this->actingAs(User::factory()->create());

    $oldRecord = CommissionRecord::factory()->create(['worked_at' => '2026-01-10']);
    $recentRecord = CommissionRecord::factory()->create(['worked_at' => '2026-02-15']);

    Livewire::test(ListCommissionRecords::class)
        ->filterTable('worked_at', [
            'from' => '2026-02-01',
            'until' => '2026-02-28',
        ])
        ->assertCanSeeTableRecords([$recentRecord])
        ->assertCanNotSeeTableRecords([$oldRecord]);
});

it('auto-completes fields when a service is selected on create form', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->percentageCommission(25.00)->create([
        'default_price' => 200.00,
    ]);

    Livewire::test(CreateCommissionRecord::class)
        ->set('data.service_id', $service->id)
        ->assertFormSet([
            'price' => '200.00',
            'commission' => '25.00',
            'commission_type' => CommissionType::Percentage,
            'calculated_earnings' => '50.00',
        ]);
});

it('clears inherited and calculated fields when service is unselected on create form', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->percentageCommission(25.00)->create([
        'default_price' => 200.00,
    ]);

    Livewire::test(CreateCommissionRecord::class)
        ->set('data.service_id', $service->id)
        ->assertFormSet([
            'price' => '200.00',
            'commission' => '25.00',
            'commission_type' => CommissionType::Percentage,
            'calculated_earnings' => '50.00',
        ])
        ->set('data.service_id', null)
        ->assertFormSet([
            'price' => null,
            'commission' => null,
            'commission_type' => CommissionType::Percentage,
            'calculated_earnings' => null,
        ]);
});

it('creates a commission record with calculated earnings via percentage', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create();

    Livewire::test(CreateCommissionRecord::class)
        ->fillForm([
            'service_id' => $service->id,
            'price' => 300.00,
            'commission_type' => CommissionType::Percentage->value,
            'commission' => 20.00,
            'calculated_earnings' => 60.00,
            'status' => CommissionStatus::Pending->value,
            'worked_at' => '2026-10-18',
            'notes' => 'Routine follow-up',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(CommissionRecord::class, [
        'service_id' => $service->id,
        'price' => 300.00,
        'commission_type' => CommissionType::Percentage->value,
        'commission' => 20.00,
        'calculated_earnings' => 60.00,
        'status' => CommissionStatus::Pending->value,
        'notes' => 'Routine follow-up',
    ]);
});

it('creates a commission record with fixed commission and observer enforces calculation', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create();

    Livewire::test(CreateCommissionRecord::class)
        ->fillForm([
            'service_id' => $service->id,
            'price' => 500.00,
            'commission_type' => CommissionType::Fixed->value,
            'commission' => 125.00,
            'calculated_earnings' => 125.00,
            'status' => CommissionStatus::Pending->value,
            'worked_at' => '2026-10-18',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(CommissionRecord::class, [
        'service_id' => $service->id,
        'price' => 500.00,
        'commission_type' => CommissionType::Fixed->value,
        'commission' => 125.00,
        'calculated_earnings' => 125.00,
    ]);
});

it('creates a commission record with fixed commission and empty price', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create();

    Livewire::test(CreateCommissionRecord::class)
        ->fillForm([
            'service_id' => $service->id,
            'price' => null,
            'commission_type' => CommissionType::Fixed->value,
            'commission' => 80.00,
            'calculated_earnings' => 80.00,
            'status' => CommissionStatus::Pending->value,
            'worked_at' => '2026-10-18',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(CommissionRecord::class, [
        'service_id' => $service->id,
        'price' => null,
        'commission_type' => CommissionType::Fixed->value,
        'commission' => 80.00,
        'calculated_earnings' => 80.00,
    ]);
});

it('requires price when commission_type is percentage', function () {
    $this->actingAs(User::factory()->create());

    $service = Service::factory()->create();

    Livewire::test(CreateCommissionRecord::class)
        ->fillForm([
            'service_id' => $service->id,
            'price' => null,
            'commission_type' => CommissionType::Percentage->value,
            'commission' => 20.00,
            'status' => CommissionStatus::Pending->value,
            'worked_at' => '2026-10-18',
        ])
        ->call('create')
        ->assertHasFormErrors(['price' => 'required'])
        ->assertNotNotified();
});

it('updates an existing commission record', function () {
    $this->actingAs(User::factory()->create());

    $record = CommissionRecord::factory()->create([
        'price' => 100.00,
        'commission' => 10.00,
        'commission_type' => CommissionType::Fixed,
    ]);

    Livewire::test(EditCommissionRecord::class, ['record' => $record->id])
        ->fillForm([
            'price' => 250.00,
            'commission_type' => CommissionType::Percentage->value,
            'commission' => 20.00,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(CommissionRecord::class, [
        'id' => $record->id,
        'price' => 250.00,
        'commission_type' => CommissionType::Percentage->value,
        'commission' => 20.00,
        'calculated_earnings' => 50.00,
    ]);
});

it('marks a single record as paid from table action', function () {
    $this->actingAs(User::factory()->create());

    $record = CommissionRecord::factory()->pending()->create();

    Livewire::test(ListCommissionRecords::class)
        ->callTableAction('markAsPaid', $record)
        ->assertNotified();

    expect($record->refresh()->status)->toBe(CommissionStatus::Paid);
});

it('marks multiple records as paid from bulk action', function () {
    $this->actingAs(User::factory()->create());

    $records = CommissionRecord::factory()->count(3)->pending()->create();

    Livewire::test(ListCommissionRecords::class)
        ->callTableBulkAction('markAsPaid', $records)
        ->assertNotified();

    foreach ($records as $record) {
        expect($record->refresh()->status)->toBe(CommissionStatus::Paid);
    }
});

it('deletes a commission record from the edit page', function () {
    $this->actingAs(User::factory()->create());

    $record = CommissionRecord::factory()->create();

    Livewire::test(EditCommissionRecord::class, ['record' => $record->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertModelMissing($record);
});

it('only displays commission records owned by the authenticated user in the list table', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userRecord = CommissionRecord::factory()->for($user)->create();
    $otherRecord = CommissionRecord::factory()->for($otherUser)->create();

    $this->actingAs($user);

    Livewire::test(ListCommissionRecords::class)
        ->assertCanSeeTableRecords([$userRecord])
        ->assertCanNotSeeTableRecords([$otherRecord]);
});

it('assigns user_id automatically to the authenticated user on create', function () {
    $user = User::factory()->create();
    $service = Service::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(CreateCommissionRecord::class)
        ->fillForm([
            'service_id' => $service->id,
            'price' => 150.00,
            'commission_type' => CommissionType::Fixed->value,
            'commission' => 30.00,
            'calculated_earnings' => 30.00,
            'status' => CommissionStatus::Pending->value,
            'worked_at' => '2026-10-20',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(CommissionRecord::class, [
        'service_id' => $service->id,
        'user_id' => $user->id,
    ]);
});

it('forbids editing a commission record owned by another user with a 404 response', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherRecord = CommissionRecord::factory()->for($otherUser)->create();

    $this->actingAs($user);

    Livewire::test(EditCommissionRecord::class, ['record' => $otherRecord->id])
        ->assertNotFound();
});
