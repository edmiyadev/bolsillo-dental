<?php

use App\Filament\Central\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Central\Resources\Tenants\Pages\EditTenant;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('central'));
    $this->actingAs(User::factory()->create());
});

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Tenant::query()->get()->each(function (Tenant $tenant): void {
        $dbPath = database_path($tenant->databaseName());
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }
    });

    foreach (glob(database_path('test_filament_tenant_*.sqlite')) ?: [] as $file) {
        @unlink($file);
    }
});

it('lists and searches clients in the central table', function (): void {
    $tenantA = Tenant::query()->create([
        'id' => 'clinica-central-a',
        'name' => 'Clínica Dental Aurora',
        'email' => 'aurora@example.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_aurora.sqlite',
    ]);

    $tenantB = Tenant::query()->create([
        'id' => 'clinica-central-b',
        'name' => 'Centro Odontológico Horizonte',
        'email' => 'horizonte@example.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_horizonte.sqlite',
    ]);

    Livewire::test(ListTenants::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$tenantA, $tenantB])
        ->searchTable('Aurora')
        ->assertCanSeeTableRecords([$tenantA])
        ->assertCanNotSeeTableRecords([$tenantB]);
});

it('creates a new client with domain, database, and initial admin user from the central panel', function (): void {
    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => 'Clínica Dental Caribe',
            'id' => 'clinica-caribe',
            'email' => 'info@clinicacaribe.com',
            'tenancy_db_name' => 'test_filament_tenant_caribe.sqlite',
            'is_active' => true,
            'domain' => 'caribe.localhost',
            'create_initial_user' => true,
            'admin_name' => 'Dr. Carlos Pérez',
            'admin_email' => 'carlos@clinicacaribe.com',
            'admin_password' => 'password1234',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Tenant::class, [
        'id' => 'clinica-caribe',
        'name' => 'Clínica Dental Caribe',
        'email' => 'info@clinicacaribe.com',
        'is_active' => true,
    ]);

    assertDatabaseHas(Domain::class, [
        'tenant_id' => 'clinica-caribe',
        'domain' => 'caribe.localhost',
    ]);

    $tenant = Tenant::query()->findOrFail('clinica-caribe');
    expect(file_exists(database_path('test_filament_tenant_caribe.sqlite')))->toBeTrue();

    $tenant->run(function (): void {
        expect(User::query()->where('email', 'carlos@clinicacaribe.com')->exists())->toBeTrue();
    });
});

it('validates required fields and unique domain when creating a client', function (): void {
    $existing = Tenant::query()->create([
        'id' => 'existente',
        'name' => 'Clínica Existente',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_existente.sqlite',
    ]);
    $existing->domains()->create(['domain' => 'duplicado.localhost']);

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'name' => null,
            'id' => 'existente',
            'tenancy_db_name' => 'nombre invalido con espacios!',
            'domain' => 'duplicado.localhost',
            'create_initial_user' => false,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'id' => 'unique',
            'tenancy_db_name' => 'regex',
            'domain' => 'unique',
        ])
        ->assertNotNotified();
});

it('updates an existing client and deletes it along with its database', function (): void {
    $tenant = Tenant::query()->create([
        'id' => 'clinica-editable',
        'name' => 'Clínica Original',
        'email' => 'original@example.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_editable.sqlite',
    ]);
    $tenant->domains()->create(['domain' => 'editable.localhost']);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->fillForm([
            'name' => 'Clínica Renombrada',
            'email' => 'renombrada@example.com',
            'is_active' => false,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Tenant::class, [
        'id' => 'clinica-editable',
        'name' => 'Clínica Renombrada',
        'email' => 'renombrada@example.com',
        'is_active' => false,
    ]);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertModelMissing($tenant);
    expect(file_exists(database_path('test_filament_tenant_editable.sqlite')))->toBeFalse();
});
