<?php

use App\Filament\Central\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Central\Resources\Tenants\Pages\EditTenant;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Models\Client;
use App\Models\Clinic;
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

it('lists and searches clinics in the central table', function (): void {
    $client = Client::factory()->create();

    $tenantA = Tenant::query()->create([
        'id' => 'clinica-central-a',
        'client_id' => $client->id,
        'name' => 'Clínica Dental Aurora',
        'email' => 'aurora@example.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_aurora.sqlite',
    ]);

    $tenantB = Tenant::query()->create([
        'id' => 'clinica-central-b',
        'client_id' => $client->id,
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

it('creates a new clinic instance associated with a client and provisions the Clinic entity in the tenant database', function (): void {
    $client = Client::factory()->create(['name' => 'Dr. Carlos Pérez']);

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'client_id' => $client->id,
            'name' => 'Clínica Dental Caribe',
            'id' => 'clinica-caribe',
            'email' => 'info@clinicacaribe.com',
            'phone' => '809-222-3344',
            'rnc' => '130987654',
            'address' => 'Av. Winston Churchill #100, Santo Domingo',
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
        'client_id' => $client->id,
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

        $clinic = Clinic::query()->first();
        expect($clinic)->not->toBeNull()
            ->and($clinic->name)->toBe('Clínica Dental Caribe')
            ->and($clinic->email)->toBe('info@clinicacaribe.com')
            ->and($clinic->phone)->toBe('809-222-3344')
            ->and($clinic->rnc)->toBe('130987654')
            ->and($clinic->address)->toBe('Av. Winston Churchill #100, Santo Domingo');
    });
});

it('validates required fields and unique domain when creating a clinic instance', function (): void {
    $client = Client::factory()->create();

    $existing = Tenant::query()->create([
        'id' => 'existente',
        'client_id' => $client->id,
        'name' => 'Clínica Existente',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_existente.sqlite',
    ]);
    $existing->domains()->create(['domain' => 'duplicado.localhost']);

    Livewire::test(CreateTenant::class)
        ->fillForm([
            'client_id' => null,
            'name' => null,
            'id' => 'existente',
            'tenancy_db_name' => 'nombre invalido con espacios!',
            'domain' => 'duplicado.localhost',
            'create_initial_user' => false,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'client_id' => 'required',
            'name' => 'required',
            'id' => 'unique',
            'tenancy_db_name' => 'regex',
            'domain' => 'unique',
        ])
        ->assertNotNotified();
});

it('updates an existing clinic instance and deletes it along with its database', function (): void {
    $client = Client::factory()->create();

    $tenant = Tenant::query()->create([
        'id' => 'clinica-editable',
        'client_id' => $client->id,
        'name' => 'Clínica Original',
        'email' => 'original@example.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_filament_tenant_editable.sqlite',
    ]);
    $tenant->domains()->create(['domain' => 'editable.localhost']);

    Livewire::test(EditTenant::class, ['record' => $tenant->id])
        ->fillForm([
            'client_id' => $client->id,
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
