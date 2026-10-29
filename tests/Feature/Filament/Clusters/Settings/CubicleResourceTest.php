<?php

use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\EditBranch;
use App\Filament\Clusters\Settings\Resources\Branches\RelationManagers\CubiclesRelationManager;
use App\Filament\Clusters\Settings\Resources\Cubicles\CubicleResource;
use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\CreateCubicle;
use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\EditCubicle;
use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\ListCubicles;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Cubicle;
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

    $this->clinic = Clinic::factory()->create([
        'name' => 'Clínica Dental Sonrisa',
        'phone' => '809-555-0100',
        'address' => 'Av. 27 de Febrero #50',
    ]);

    $this->branch = Branch::factory()->main()->create([
        'clinic_id' => $this->clinic->id,
        'name' => 'Sede Principal',
        'code' => 'MAIN',
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole(RoleEnum::SuperAdmin->value);
    $this->actingAs($this->user);
});

it('can render the cubicle list page inside the settings cluster', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo 1 - General',
    ]);

    $this->get(CubicleResource::getUrl('index'))
        ->assertOk();

    Livewire::test(ListCubicles::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$cubicle]);
});

it('maintains cluster sub navigation on create and edit cubicle pages', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    $createPage = Livewire::test(CreateCubicle::class);
    expect($createPage->instance()->getSubNavigation())->not->toBeEmpty();

    $editPage = Livewire::test(EditCubicle::class, ['record' => $cubicle->id]);
    expect($editPage->instance()->getSubNavigation())->not->toBeEmpty();
});

it('can create a cubicle with auto-generated code and default specialties', function (): void {
    $this->get(CubicleResource::getUrl('create'))
        ->assertOk();

    Livewire::test(CreateCubicle::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'name' => 'Cubículo 1 - General',
            'status' => CubicleStatus::Active->value,
            'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Cubicle::class, [
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo 1 - General',
        'code' => 'CUB-01',
        'status' => CubicleStatus::Active->value,
    ]);
});

it('auto-generates sequential codes per branch and resets for another branch', function (): void {
    $cubicle1 = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo 1',
    ]);

    $cubicle2 = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo 2',
    ]);

    expect($cubicle1->code)->toBe('CUB-01')
        ->and($cubicle2->code)->toBe('CUB-02');

    $secondBranch = Branch::factory()->create([
        'clinic_id' => $this->clinic->id,
        'name' => 'Sucursal Santiago',
        'code' => 'STG',
    ]);

    $cubicleBranch2 = Cubicle::factory()->create([
        'branch_id' => $secondBranch->id,
        'name' => 'Cubículo Santiago 1',
    ]);

    expect($cubicleBranch2->code)->toBe('CUB-01');
});

it('can create and edit a cubicle with specialty schedule rules', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Sillón Mixto',
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
        'specialty_schedules' => [
            [
                'day' => DayOfWeek::Thursday->value,
                'specialty' => DentalSpecialty::Orthodontics->value,
                'start_time' => '08:00',
                'end_time' => '18:00',
            ],
        ],
    ]);

    Livewire::test(EditCubicle::class, ['record' => $cubicle->id])
        ->assertOk()
        ->assertFormSet([
            'name' => 'Sillón Mixto',
            'status' => CubicleStatus::Active,
        ])
        ->fillForm([
            'name' => 'Sillón Mixto A1',
            'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value, DentalSpecialty::Endodontics->value],
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    $cubicle->refresh();
    expect($cubicle->name)->toBe('Sillón Mixto A1')
        ->and($cubicle->specialty_types)->toContain(DentalSpecialty::Endodontics->value);
});

it('correctly determines whether a cubicle supports a specialty', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
    ]);

    expect($cubicle->supportsSpecialty(DentalSpecialty::General))->toBeTrue()
        ->and($cubicle->supportsSpecialty(DentalSpecialty::Orthodontics))->toBeTrue()
        ->and($cubicle->supportsSpecialty(DentalSpecialty::OralSurgery))->toBeFalse();
});

it('can manage cubicles from the relation manager inside branch edit page', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo Sillón 1',
    ]);

    Livewire::test(CubiclesRelationManager::class, [
        'ownerRecord' => $this->branch,
        'pageClass' => EditBranch::class,
    ])
        ->assertCanSeeTableRecords([$cubicle]);
});

it('can create a cubicle with specialty schedule rules via form', function (): void {
    Livewire::test(CreateCubicle::class)
        ->fillForm([
            'branch_id' => $this->branch->id,
            'name' => 'Sillón Ortodoncia Exclusivo',
            'status' => CubicleStatus::Active->value,
            'specialty_types' => [DentalSpecialty::General->value, DentalSpecialty::Orthodontics->value],
            'specialty_schedules' => [
                [
                    'day' => DayOfWeek::Thursday->value,
                    'specialty' => DentalSpecialty::Orthodontics->value,
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Cubicle::class, [
        'branch_id' => $this->branch->id,
        'name' => 'Sillón Ortodoncia Exclusivo',
        'code' => 'CUB-01',
    ]);

    $created = Cubicle::query()->where('name', 'Sillón Ortodoncia Exclusivo')->firstOrFail();
    expect($created->specialty_schedules)->toHaveCount(1)
        ->and($created->specialty_schedules[0]['day'])->toBe('thursday')
        ->and($created->specialty_schedules[0]['specialty'])->toBe('orthodontics');
});

it('supports soft delete, restore, and force delete on cubicles', function (): void {
    $cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
    ]);

    // 1. Soft delete
    Livewire::test(EditCubicle::class, ['record' => $cubicle->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($cubicle);
    expect($cubicle->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListCubicles::class)
        ->assertCanNotSeeTableRecords([$cubicle])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$cubicle]);

    // 3. Restore
    Livewire::test(EditCubicle::class, ['record' => $cubicle->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($cubicle->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $cubicle->delete();
    Livewire::test(EditCubicle::class, ['record' => $cubicle->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($cubicle);
});
