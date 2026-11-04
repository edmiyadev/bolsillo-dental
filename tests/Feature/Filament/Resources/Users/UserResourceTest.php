<?php

use App\Enums\DayOfWeek;
use App\Enums\RoleEnum;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Role;
use App\Models\User;
use App\Support\ModelHelper;
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

it('renders the view collaborator page with assigned branches and working days summary', function (): void {
    $this->actingAs($this->superAdmin);

    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sede Principal Naco',
    ]);

    $collaborator = User::factory()->create([
        'name' => 'Dr. Marcos Santos',
        'email' => 'marcos@bolsillodental.test',
    ]);
    $collaborator->assignRole(RoleEnum::Dentist->value);

    $collaborator->branchSchedules()->create([
        'branch_id' => $mainBranch->id,
        'is_active' => true,
        'schedule' => array_map(
            fn (DayOfWeek $day): array => [
                'day' => $day->value,
                'is_open' => in_array($day, [DayOfWeek::Monday, DayOfWeek::Wednesday, DayOfWeek::Friday], true),
                'open_time' => in_array($day, [DayOfWeek::Monday, DayOfWeek::Wednesday, DayOfWeek::Friday], true) ? '08:00' : null,
                'close_time' => in_array($day, [DayOfWeek::Monday, DayOfWeek::Wednesday, DayOfWeek::Friday], true) ? '14:00' : null,
            ],
            DayOfWeek::cases(),
        ),
    ]);

    Livewire::test(ViewUser::class, ['record' => $collaborator->id])
        ->assertOk()
        ->assertSee('Dr. Marcos Santos')
        ->assertSee('marcos@bolsillodental.test')
        ->assertSee('Sede Principal Naco')
        ->assertSee(DayOfWeek::Monday->getLabel())
        ->assertSee(DayOfWeek::Wednesday->getLabel())
        ->assertSee(DayOfWeek::Friday->getLabel())
        ->assertSee('08:00')
        ->assertSee('14:00')
        ->assertDontSee(DayOfWeek::Tuesday->getLabel())
        ->assertDontSee(DayOfWeek::Sunday->getLabel());
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

it('assigns multiple branches and non-overlapping schedules to a collaborator', function (): void {
    $this->actingAs($this->superAdmin);

    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sede Principal Naco',
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);
    $piantiniBranch = Branch::factory()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sucursal Piantini',
        'code' => 'PIAN-01',
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $morningSchedule = array_map(
        fn (DayOfWeek $day): array => [
            'day' => $day->value,
            'is_open' => $day === DayOfWeek::Monday,
            'open_time' => $day === DayOfWeek::Monday ? '08:00' : null,
            'close_time' => $day === DayOfWeek::Monday ? '12:00' : null,
        ],
        DayOfWeek::cases(),
    );

    $afternoonSchedule = array_map(
        fn (DayOfWeek $day): array => [
            'day' => $day->value,
            'is_open' => $day === DayOfWeek::Monday,
            'open_time' => $day === DayOfWeek::Monday ? '13:00' : null,
            'close_time' => $day === DayOfWeek::Monday ? '18:00' : null,
        ],
        DayOfWeek::cases(),
    );

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Dr. Roberto Méndez',
            'email' => 'roberto@bolsillodental.test',
            'password' => 'secret1234',
            'branchSchedules' => [
                [
                    'branch_id' => $mainBranch->id,
                    'is_active' => true,
                    'schedule' => $morningSchedule,
                ],
                [
                    'branch_id' => $piantiniBranch->id,
                    'is_active' => true,
                    'schedule' => $afternoonSchedule,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $collaborator = User::where('email', 'roberto@bolsillodental.test')->firstOrFail();

    expect($collaborator->branches)->toHaveCount(2)
        ->and($collaborator->branchSchedules)->toHaveCount(2)
        ->and($collaborator->branches->pluck('id')->all())->toEqualCanonicalizing([$mainBranch->id, $piantiniBranch->id]);
});

it('fails validation when collaborator schedule is outside branch opening hours or on a closed day', function (): void {
    $this->actingAs($this->superAdmin);

    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sede Principal Naco',
        'opening_hours' => DayOfWeek::defaultSchedule(), // 08:00 - 18:00 Mon-Fri, closed Sunday
    ]);

    $collaborator = User::factory()->create([
        'name' => 'Dra. Elena Gómez',
        'email' => 'elena@bolsillodental.test',
    ]);

    $outOfHoursSchedule = array_map(
        fn (DayOfWeek $day): array => [
            'day' => $day->value,
            'is_open' => $day === DayOfWeek::Monday,
            'open_time' => $day === DayOfWeek::Monday ? '07:00' : null,
            'close_time' => $day === DayOfWeek::Monday ? '20:00' : null,
        ],
        DayOfWeek::cases(),
    );

    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->fillForm([
            'branchSchedules' => [
                [
                    'branch_id' => $mainBranch->id,
                    'is_active' => true,
                    'schedule' => $outOfHoursSchedule,
                ],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['branchSchedules']);
});

it('fails validation when collaborator schedules overlap on the same day across two active branches', function (): void {
    $this->actingAs($this->superAdmin);

    $clinic = Clinic::factory()->create();
    $mainBranch = Branch::factory()->main()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sede Principal Naco',
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);
    $piantiniBranch = Branch::factory()->create([
        'clinic_id' => $clinic->id,
        'name' => 'Sucursal Piantini',
        'code' => 'PIAN-01',
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $collaborator = User::factory()->create([
        'name' => 'Dr. Luis Herrera',
        'email' => 'luis@bolsillodental.test',
    ]);

    $scheduleBranchA = array_map(
        fn (DayOfWeek $day): array => [
            'day' => $day->value,
            'is_open' => $day === DayOfWeek::Tuesday,
            'open_time' => $day === DayOfWeek::Tuesday ? '08:00' : null,
            'close_time' => $day === DayOfWeek::Tuesday ? '14:00' : null,
        ],
        DayOfWeek::cases(),
    );

    $scheduleBranchB = array_map(
        fn (DayOfWeek $day): array => [
            'day' => $day->value,
            'is_open' => $day === DayOfWeek::Tuesday,
            'open_time' => $day === DayOfWeek::Tuesday ? '12:00' : null,
            'close_time' => $day === DayOfWeek::Tuesday ? '17:00' : null,
        ],
        DayOfWeek::cases(),
    );

    Livewire::test(EditUser::class, ['record' => $collaborator->id])
        ->fillForm([
            'branchSchedules' => [
                [
                    'branch_id' => $mainBranch->id,
                    'is_active' => true,
                    'schedule' => $scheduleBranchA,
                ],
                [
                    'branch_id' => $piantiniBranch->id,
                    'is_active' => true,
                    'schedule' => $scheduleBranchB,
                ],
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['branchSchedules']);
});

it('excludes BranchUser from ModelHelper::getModelsWithExclusions', function (): void {
    $models = ModelHelper::getModelsWithExclusions();

    expect($models)->not->toContain('BranchUser');
});
