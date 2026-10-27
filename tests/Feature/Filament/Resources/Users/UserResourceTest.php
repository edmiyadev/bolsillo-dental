<?php

use App\Enums\RoleEnum;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->superAdmin = User::factory()->create([
        'name' => 'Dr. Administrador Principal',
        'email' => 'admin@bolsillodental.test',
    ]);
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
});

it('uses collaborator labels in spanish and english', function (): void {
    app()->setLocale('es');

    expect(UserResource::getModelLabel())->toBe('Colaborador')
        ->and(UserResource::getPluralModelLabel())->toBe('Colaboradores')
        ->and(UserResource::getNavigationLabel())->toBe('Colaboradores');

    app()->setLocale('en');

    expect(UserResource::getModelLabel())->toBe('Collaborator')
        ->and(UserResource::getPluralModelLabel())->toBe('Collaborators')
        ->and(UserResource::getNavigationLabel())->toBe('Collaborators');
});

it('excludes users with super_admin role from list query and table', function (): void {
    $this->actingAs($this->superAdmin);

    $collaborator = User::factory()->create([
        'name' => 'Dra. Carmen Reyes',
        'email' => 'carmen@bolsillodental.test',
    ]);
    $collaborator->assignRole(RoleEnum::Dentist->value);

    expect(UserResource::getEloquentQuery()->where('id', $this->superAdmin->id)->exists())->toBeFalse()
        ->and(UserResource::getEloquentQuery()->where('id', $collaborator->id)->exists())->toBeTrue();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$collaborator])
        ->assertCanNotSeeTableRecords([$this->superAdmin]);
});

it('creates a new collaborator and assigns tenant roles', function (): void {
    $this->actingAs($this->superAdmin);

    $dentistRole = Role::findByName(RoleEnum::Dentist->value, 'web');

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Dr. Fernando Vega',
            'email' => 'fernando@bolsillodental.test',
            'password' => 'secret1234',
            'roles' => [$dentistRole->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Dr. Fernando Vega',
        'email' => 'fernando@bolsillodental.test',
    ]);

    $createdUser = User::where('email', 'fernando@bolsillodental.test')->firstOrFail();
    expect($createdUser->hasRole(RoleEnum::Dentist->value))->toBeTrue()
        ->and(Hash::check('secret1234', $createdUser->password))->toBeTrue();
});

it('edits an existing collaborator and updates roles', function (): void {
    $this->actingAs($this->superAdmin);

    $collaborator = User::factory()->create([
        'name' => 'Lic. Ana Soto',
        'email' => 'ana@bolsillodental.test',
    ]);
    $receptionistRole = Role::findByName(RoleEnum::Receptionist->value, 'web');
    $dentalAssistantRole = Role::findByName(RoleEnum::DentalAssistant->value, 'web');
    $collaborator->assignRole($receptionistRole);

    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->fillForm([
            'name' => 'Lic. Ana Soto Actualizada',
            'email' => 'ana.actualizada@bolsillodental.test',
            'roles' => [$dentalAssistantRole->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $collaborator->refresh();

    expect($collaborator->name)->toBe('Lic. Ana Soto Actualizada')
        ->and($collaborator->email)->toBe('ana.actualizada@bolsillodental.test')
        ->and($collaborator->hasRole(RoleEnum::DentalAssistant->value))->toBeTrue()
        ->and($collaborator->hasRole(RoleEnum::Receptionist->value))->toBeFalse();
});

it('renders the view collaborator page with back and action buttons', function (): void {
    $this->actingAs($this->superAdmin);

    $collaborator = User::factory()->create([
        'name' => 'Dr. Marcos Santos',
        'email' => 'marcos@bolsillodental.test',
    ]);
    $collaborator->assignRole(RoleEnum::Dentist->value);

    Livewire::test(ViewUser::class, ['record' => $collaborator->id])
        ->assertOk()
        ->assertSee('Dr. Marcos Santos')
        ->assertSee('marcos@bolsillodental.test');
});

it('supports soft delete, restore, and force delete on collaborators', function (): void {
    $this->actingAs($this->superAdmin);

    $collaborator = User::factory()->create([
        'name' => 'Colaborador Temporal',
        'email' => 'temporal@bolsillodental.test',
    ]);

    // 1. Soft delete
    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertSoftDeleted($collaborator);
    expect($collaborator->fresh()->trashed())->toBeTrue();

    // 2. Restore
    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    expect($collaborator->fresh()->trashed())->toBeFalse();

    // 3. Force delete
    $collaborator->delete();
    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->callAction(ForceDeleteAction::class)
        ->assertNotified();

    assertModelMissing($collaborator);
});

it('controls TrashedFilter visibility based on restore and force_delete permissions', function (): void {
    // 1. User with permissions (Super Admin) sees the trashed filter
    $this->actingAs($this->superAdmin);

    Livewire::test(ListUsers::class)
        ->assertTableFilterVisible('trashed');

    // 2. User without restore/force_delete permissions cannot see the trashed filter
    $restrictedUser = User::factory()->create([
        'name' => 'Usuario Limitado',
        'email' => 'limitado@bolsillodental.test',
    ]);
    $restrictedUser->givePermissionTo(Permission::findByName('view_users', 'web'));

    $this->actingAs($restrictedUser);

    Livewire::test(ListUsers::class)
        ->assertTableFilterHidden('trashed');
});

it('prevents a collaborator from deleting or force deleting themselves via UserPolicy', function (): void {
    $collaborator = User::factory()->create([
        'name' => 'Dr. Auto Protegido',
        'email' => 'protegido@bolsillodental.test',
    ]);

    $collaborator->givePermissionTo([
        Permission::findByName('delete_users', 'web'),
        Permission::findByName('force_delete_users', 'web'),
    ]);

    $otherUser = User::factory()->create([
        'name' => 'Otro Colaborador',
        'email' => 'otro@bolsillodental.test',
    ]);

    // Cannot delete or force delete self
    expect($collaborator->can('delete', $collaborator))->toBeFalse()
        ->and($collaborator->can('forceDelete', $collaborator))->toBeFalse();

    // Can delete or force delete another user
    expect($collaborator->can('delete', $otherUser))->toBeTrue()
        ->and($collaborator->can('forceDelete', $otherUser))->toBeTrue();
});
