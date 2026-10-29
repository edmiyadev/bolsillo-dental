<?php

use App\Filament\Clusters\Settings\Resources\Branches\BranchResource;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\CreateBranch;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\EditBranch;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\ListBranches;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->clinic = Clinic::factory()->create([
        'name' => 'Clínica Dental Sonrisa',
        'phone' => '809-555-0100',
        'address' => 'Av. 27 de Febrero #50',
    ]);

    $this->mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $this->clinic->id,
        'phone' => '809-555-0100',
        'address' => 'Av. 27 de Febrero #50',
    ]);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('can render the branch list page inside the settings cluster', function (): void {
    $this->get(BranchResource::getUrl('index'))
        ->assertOk();

    Livewire::test(ListBranches::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->mainBranch]);
});

it('maintains cluster sub navigation on create and edit pages', function (): void {
    $createPage = Livewire::test(CreateBranch::class);
    expect($createPage->instance()->getSubNavigation())->not->toBeEmpty();

    $editPage = Livewire::test(EditBranch::class, ['record' => $this->mainBranch->id]);
    expect($editPage->instance()->getSubNavigation())->not->toBeEmpty();
});

it('can create a new branch with custom hours', function (): void {
    $this->get(BranchResource::getUrl('create'))
        ->assertOk();

    Livewire::test(CreateBranch::class)
        ->fillForm([
            'name' => 'Sucursal Naco',
            'code' => 'NACO-01',
            'phone' => '809-555-9988',
            'address' => 'Calle Fantino Falco #40',
            'is_main' => false,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Branch::class, [
        'clinic_id' => $this->clinic->id,
        'name' => 'Sucursal Naco',
        'code' => 'NACO-01',
        'phone' => '809-555-9988',
        'is_main' => false,
        'is_active' => true,
    ]);
});

it('can edit an existing branch', function (): void {
    $this->get(BranchResource::getUrl('edit', ['record' => $this->mainBranch]))
        ->assertOk();

    Livewire::test(EditBranch::class, ['record' => $this->mainBranch->id])
        ->assertOk()
        ->assertFormSet([
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'phone' => '809-555-0100',
        ])
        ->fillForm([
            'name' => 'Sede Central Renovada',
            'phone' => '809-555-0199',
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Branch::class, [
        'id' => $this->mainBranch->id,
        'name' => 'Sede Central Renovada',
        'phone' => '809-555-0199',
    ]);
});

it('prevents deactivating the only active branch', function (): void {
    expect($this->clinic->branches()->where('is_active', true)->count())->toBe(1);

    Livewire::test(EditBranch::class, ['record' => $this->mainBranch->id])
        ->fillForm([
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasFormErrors(['is_active']);

    expect($this->mainBranch->refresh()->is_active)->toBeTrue();
});

it('allows deactivating a branch when another active branch exists', function (): void {
    $secondBranch = Branch::factory()->create([
        'clinic_id' => $this->clinic->id,
        'name' => 'Sucursal Bella Vista',
        'code' => 'BV-01',
        'is_active' => true,
    ]);

    expect($this->clinic->branches()->where('is_active', true)->count())->toBe(2);

    Livewire::test(EditBranch::class, ['record' => $secondBranch->id])
        ->fillForm([
            'is_active' => false,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($secondBranch->refresh()->is_active)->toBeFalse();
});

it('automatically unsets previous main branch when a new main branch is designated', function (): void {
    $secondBranch = Branch::factory()->create([
        'clinic_id' => $this->clinic->id,
        'name' => 'Sucursal Piantini',
        'code' => 'PIAN-01',
        'is_main' => false,
    ]);

    expect($this->mainBranch->refresh()->is_main)->toBeTrue();

    Livewire::test(EditBranch::class, ['record' => $secondBranch->id])
        ->fillForm([
            'is_main' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($secondBranch->refresh()->is_main)->toBeTrue()
        ->and($this->mainBranch->refresh()->is_main)->toBeFalse();
});

it('blocks deleting the only branch of the clinic', function (): void {
    expect($this->clinic->branches()->count())->toBe(1);

    expect(fn () => $this->mainBranch->delete())
        ->toThrow(DomainException::class);
});

it('supports soft delete, restore, and force delete on branches when multiple branches exist', function (): void {
    $secondBranch = Branch::factory()->create([
        'clinic_id' => $this->clinic->id,
        'name' => 'Sucursal Bella Vista',
        'code' => 'BV-01',
    ]);

    // 1. Soft delete
    Livewire::test(EditBranch::class, ['record' => $secondBranch->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($secondBranch);
    expect($secondBranch->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListBranches::class)
        ->assertCanNotSeeTableRecords([$secondBranch])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$secondBranch]);

    // 3. Restore
    Livewire::test(EditBranch::class, ['record' => $secondBranch->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($secondBranch->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $secondBranch->delete();
    Livewire::test(EditBranch::class, ['record' => $secondBranch->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($secondBranch);
});
