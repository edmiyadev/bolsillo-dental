<?php

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\RoleEnum;
use App\Filament\Resources\CommissionRecords\Pages\CreateCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\EditCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\ListCommissionRecords;
use App\Models\CommissionRecord;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);
});

it('lists and searches commission records in the table', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

    $pending = CommissionRecord::factory()->pending()->create();
    $paid = CommissionRecord::factory()->paid()->create();

    Livewire::test(ListCommissionRecords::class)
        ->assertCanSeeTableRecords([$pending, $paid])
        ->filterTable('status', CommissionStatus::Pending->value)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$paid]);
});

it('filters commission records by worked_at date range', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

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
    $this->actingAs(User::factory()->superAdmin()->create());

    $record = CommissionRecord::factory()->pending()->create();

    Livewire::test(ListCommissionRecords::class)
        ->callTableAction('markAsPaid', $record)
        ->assertNotified();

    expect($record->refresh()->status)->toBe(CommissionStatus::Paid);
});

it('marks multiple records as paid from bulk action', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $records = CommissionRecord::factory()->count(3)->pending()->create();

    Livewire::test(ListCommissionRecords::class)
        ->callTableBulkAction('markAsPaid', $records)
        ->assertNotified();

    foreach ($records as $record) {
        expect($record->refresh()->status)->toBe(CommissionStatus::Paid);
    }
});

it('soft deletes a commission record from the edit page and supports restore and force delete', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $record = CommissionRecord::factory()->for($user)->create();

    // 1. Soft delete
    Livewire::test(EditCommissionRecord::class, ['record' => $record->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($record);
    expect($record->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListCommissionRecords::class)
        ->assertCanNotSeeTableRecords([$record])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$record]);

    // 3. Restore
    Livewire::test(EditCommissionRecord::class, ['record' => $record->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($record->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $record->delete();
    Livewire::test(EditCommissionRecord::class, ['record' => $record->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($record);
});

it('only displays commission records owned by the authenticated user in the list table when lacking view_all_commission_records', function () {
    $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');
    $user = User::factory()->create();
    $user->assignRole($dentistRole);

    $otherUser = User::factory()->create();
    $otherUser->assignRole($dentistRole);

    $userRecord = CommissionRecord::factory()->create(['created_by' => $user->id]);
    $otherRecord = CommissionRecord::factory()->create(['created_by' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test(ListCommissionRecords::class)
        ->assertCanSeeTableRecords([$userRecord])
        ->assertCanNotSeeTableRecords([$otherRecord]);
});

it('displays all commission records in the list table for user with view_all_commission_records', function () {
    $user = User::factory()->superAdmin()->create();
    $otherUser = User::factory()->create();

    $userRecord = CommissionRecord::factory()->create(['created_by' => $user->id]);
    $otherRecord = CommissionRecord::factory()->create(['created_by' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test(ListCommissionRecords::class)
        ->assertCanSeeTableRecords([$userRecord, $otherRecord]);
});

it('assigns created_by and updated_by automatically to the authenticated user on create', function () {
    $user = User::factory()->superAdmin()->create();
    $service = Service::factory()->create(['created_by' => $user->id]);

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
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
});

it('forbids editing a commission record owned by another user with a 404 response when lacking view_all_commission_records', function () {
    $dentistRole = Role::findOrCreate(RoleEnum::Dentist->value, 'web');
    $user = User::factory()->create();
    $user->assignRole($dentistRole);

    $otherUser = User::factory()->create();
    $otherUser->assignRole($dentistRole);

    $otherRecord = CommissionRecord::factory()->create(['created_by' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test(EditCommissionRecord::class, ['record' => $otherRecord->id])
        ->assertNotFound();
});

it('calculates table column summaries for total, paid, and pending commissions', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $paid = CommissionRecord::factory()->paid()->create(['calculated_earnings' => 1500.00]);
    $pending = CommissionRecord::factory()->pending()->create(['calculated_earnings' => 500.00]);
    $cancelled = CommissionRecord::factory()->cancelled()->create(['calculated_earnings' => 300.00]);

    $component = Livewire::test(ListCommissionRecords::class)
        ->assertTableColumnSummarySet('calculated_earnings', 'total', 2000.00)
        ->assertTableColumnSummarySet('calculated_earnings', 'paid', 1500.00)
        ->assertTableColumnSummarySet('calculated_earnings', 'pending', 500.00)
        ->assertTableColumnSummarySet('calculated_earnings', 'cancelled', 300.00);

    $column = $component->instance()->getTable()->getColumn('calculated_earnings');
    $query = $component->instance()->getAllTableSummaryQuery();
    expect($column->getSummarizer('cancelled')->query($query)->isVisible())->toBeTrue();
});

it('calculates table column summaries respecting active table filters', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    CommissionRecord::factory()->paid()->create([
        'calculated_earnings' => 1500.00,
        'worked_at' => '2026-02-15',
    ]);
    CommissionRecord::factory()->pending()->create([
        'calculated_earnings' => 500.00,
        'worked_at' => '2026-02-16',
    ]);
    // Outside filter range:
    CommissionRecord::factory()->paid()->create([
        'calculated_earnings' => 2000.00,
        'worked_at' => '2026-01-10',
    ]);

    Livewire::test(ListCommissionRecords::class)
        ->filterTable('worked_at', [
            'from' => '2026-02-01',
            'until' => '2026-02-28',
        ])
        ->assertTableColumnSummarySet('calculated_earnings', 'total', 2000.00)
        ->assertTableColumnSummarySet('calculated_earnings', 'paid', 1500.00)
        ->assertTableColumnSummarySet('calculated_earnings', 'pending', 500.00);
});

it('hides cancelled summary when there are no cancelled commissions', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    CommissionRecord::factory()->paid()->create(['calculated_earnings' => 1000.00]);
    CommissionRecord::factory()->pending()->create(['calculated_earnings' => 500.00]);

    $component = Livewire::test(ListCommissionRecords::class);
    $column = $component->instance()->getTable()->getColumn('calculated_earnings');
    $query = $component->instance()->getAllTableSummaryQuery();

    $cancelledSummarizer = $column->getSummarizer('cancelled');
    expect($cancelledSummarizer)->not->toBeNull();
    expect($cancelledSummarizer->query($query)->isVisible())->toBeFalse();
});

