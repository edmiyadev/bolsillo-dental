<?php

use App\Enums\RoleEnum;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Pages\ViewRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->superAdmin = User::factory()->create([
        'name' => 'Super Administrator',
        'email' => 'superadmin@bolsillodental.test',
    ]);
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
});

it('excludes super_admin role from the role list and eloquent query', function (): void {
    $this->actingAs($this->superAdmin);

    $superAdminRole = Role::findByName(RoleEnum::SuperAdmin->value, 'web');
    $adminRole = Role::findByName(RoleEnum::Admin->value, 'web');
    $dentistRole = Role::findByName(RoleEnum::Dentist->value, 'web');

    expect(RoleResource::getEloquentQuery()->where('name', RoleEnum::SuperAdmin->value)->exists())->toBeFalse()
        ->and(RoleResource::getEloquentQuery()->where('name', RoleEnum::Admin->value)->exists())->toBeTrue();

    Livewire::test(ListRoles::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$adminRole, $dentistRole])
        ->assertCanNotSeeTableRecords([$superAdminRole]);
});

it('creates a custom role and assigns module permissions via the form', function (): void {
    $this->actingAs($this->superAdmin);

    $viewUsersPerm = Permission::findByName('view_users', 'web');
    $createUsersPerm = Permission::findByName('create_users', 'web');

    Livewire::test(CreateRole::class)
        ->fillForm([
            'name' => 'Auditor Clínico',
            'permissions_users' => [$viewUsersPerm->id, $createUsersPerm->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(Role::class, [
        'name' => 'Auditor Clínico',
        'guard_name' => 'web',
    ]);

    $createdRole = Role::findByName('Auditor Clínico', 'web');
    expect($createdRole->hasPermissionTo('view_users'))->toBeTrue()
        ->and($createdRole->hasPermissionTo('create_users'))->toBeTrue()
        ->and($createdRole->hasPermissionTo('delete_users'))->toBeFalse();
});

it('renders the view role page with record details', function (): void {
    $this->actingAs($this->superAdmin);

    $customRole = Role::create([
        'name' => 'Supervisor Dental',
        'guard_name' => 'web',
    ]);

    Livewire::test(ViewRole::class, ['record' => $customRole->id])
        ->assertOk()
        ->assertSee('Supervisor Dental');
});

it('edits a role and updates its permissions', function (): void {
    $this->actingAs($this->superAdmin);

    $viewUsersPerm = Permission::findByName('view_users', 'web');
    $updateUsersPerm = Permission::findByName('update_users', 'web');

    $role = Role::create([
        'name' => 'Asistente Junior',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($viewUsersPerm);

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm([
            'name' => 'Asistente Senior',
            'permissions_users' => [$updateUsersPerm->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $role->refresh();

    expect($role->name)->toBe('Asistente Senior')
        ->and($role->hasPermissionTo('update_users'))->toBeTrue()
        ->and($role->hasPermissionTo('view_users'))->toBeFalse();
});

it('protects super_admin role from update or delete via policy and gate', function (): void {
    $this->actingAs($this->superAdmin);

    $superAdminRole = Role::findByName(RoleEnum::SuperAdmin->value, 'web');
    $customRole = Role::create([
        'name' => 'Rol Modificable',
        'guard_name' => 'web',
    ]);

    // Gate::before and RolePolicy explicitly block update, delete, and forceDelete on super_admin
    expect(Gate::allows('update', $superAdminRole))->toBeFalse()
        ->and(Gate::allows('delete', $superAdminRole))->toBeFalse()
        ->and(Gate::allows('forceDelete', $superAdminRole))->toBeFalse()
        ->and($this->superAdmin->can('update', $superAdminRole))->toBeFalse()
        ->and($this->superAdmin->can('delete', $superAdminRole))->toBeFalse()
        ->and($this->superAdmin->can('forceDelete', $superAdminRole))->toBeFalse();

    // Normal roles can be updated and deleted by super admin
    expect(Gate::allows('update', $customRole))->toBeTrue()
        ->and(Gate::allows('delete', $customRole))->toBeTrue()
        ->and($this->superAdmin->can('update', $customRole))->toBeTrue()
        ->and($this->superAdmin->can('delete', $customRole))->toBeTrue();
});

it('supports soft delete, restore, and force delete on roles', function (): void {
    $this->actingAs($this->superAdmin);

    $role = Role::create([
        'name' => 'Rol Descartable',
        'guard_name' => 'web',
    ]);

    // 1. Soft delete
    Livewire::test(EditRole::class, ['record' => $role->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertDatabaseMissing(Role::class, [
        'id' => $role->id,
        'deleted_at' => null,
    ]);
    assertSoftDeleted($role);
    expect($role->fresh()->trashed())->toBeTrue();

    // 2. Filter trashed in list table
    Livewire::test(ListRoles::class)
        ->assertCanNotSeeTableRecords([$role])
        ->filterTable('trashed', true)
        ->assertCanSeeTableRecords([$role]);

    // 3. Restore
    Livewire::test(EditRole::class, ['record' => $role->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($role->fresh()->trashed())->toBeFalse();

    // 4. Force delete
    $role->delete();
    Livewire::test(EditRole::class, ['record' => $role->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($role);
});
