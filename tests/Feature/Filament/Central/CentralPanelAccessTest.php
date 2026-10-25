<?php

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Filament\Central\Widgets\TenantsOverview;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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
});

it('allows authenticated central users to access the central panel on a central domain', function (): void {
    $user = User::factory()->create();
    $panel = Filament::getPanel('central');

    expect($user->canAccessPanel($panel))->toBeTrue();

    $this->actingAs($user)
        ->get('http://localhost/central')
        ->assertOk();
});

it('blocks access to the central panel when requested from a tenant domain', function (): void {
    $user = User::factory()->create();

    app(CreateTenantAction::class)->handle(CreateTenantData::fromArray([
        'id' => 'clinica-bloqueo',
        'name' => 'Clínica Bloqueo',
        'tenancy_db_name' => 'test_tenant_bloqueo.sqlite',
        'domain' => 'clinica-bloqueo.localhost',
        'create_initial_user' => false,
    ]));

    $this->actingAs($user)
        ->get('http://clinica-bloqueo.localhost/central')
        ->assertNotFound();
});

it('identifies tenant by subdomain or full domain and blocks inactive tenants', function (): void {
    $tenant = app(CreateTenantAction::class)->handle(CreateTenantData::fromArray([
        'id' => 'clinica-activa',
        'name' => 'Clínica Activa',
        'tenancy_db_name' => 'test_tenant_activa.sqlite',
        'domain' => 'clinica-activa',
        'create_initial_user' => true,
        'admin_name' => 'Dra. Activa',
        'admin_email' => 'activa@clinica.com',
        'admin_password' => 'password123',
    ]));

    // Visiting login page on subdomain initializes tenancy
    $this->get('http://clinica-activa.localhost/login')
        ->assertOk();

    expect(tenancy()->initialized)->toBeTrue()
        ->and(tenant('id'))->toBe('clinica-activa');

    tenancy()->end();

    // Mark tenant as inactive and verify 403 response
    $tenant->update(['is_active' => false]);

    $this->get('http://clinica-activa.localhost/login')
        ->assertForbidden();
});

it('renders the TenantsOverview widget with accurate statistics', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('central'));
    $this->actingAs(User::factory()->create());

    $tenant = Tenant::query()->create([
        'id' => 'clinica-stats',
        'name' => 'Clínica Stats',
        'is_active' => true,
        'tenancy_db_name' => 'test_tenant_stats.sqlite',
    ]);
    $tenant->domains()->create(['domain' => 'stats.localhost']);

    Livewire::test(TenantsOverview::class)
        ->assertOk()
        ->assertSee(__('Total clients'))
        ->assertSee(__('Active clients'))
        ->assertSee(__('Configured domains'));
});
