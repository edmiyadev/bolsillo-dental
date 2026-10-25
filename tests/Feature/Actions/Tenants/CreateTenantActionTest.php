<?php

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Models\CommissionRecord;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('creates a tenant, its domain, its physical database, and provisions the initial user with default services', function (): void {
    $data = CreateTenantData::fromArray([
        'id' => 'clinica-sonrisa',
        'name' => 'Clínica Dental Sonrisa',
        'email' => 'contacto@clinicasonrisa.com',
        'is_active' => true,
        'tenancy_db_name' => 'test_tenant_clinica_sonrisa.sqlite',
        'domain' => 'clinica-sonrisa.localhost',
        'create_initial_user' => true,
        'admin_name' => 'Dra. María Gómez',
        'admin_email' => 'maria@clinicasonrisa.com',
        'admin_password' => 'secret1234',
    ]);

    $tenant = app(CreateTenantAction::class)->handle($data);

    expect($tenant->id)->toBe('clinica-sonrisa')
        ->and($tenant->name)->toBe('Clínica Dental Sonrisa')
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->databaseName())->toBe('test_tenant_clinica_sonrisa.sqlite')
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['clinica-sonrisa.localhost'])
        ->and(file_exists(database_path('test_tenant_clinica_sonrisa.sqlite')))->toBeTrue();

    // Central database should not contain the tenant's user
    expect(User::query()->where('email', 'maria@clinicasonrisa.com')->exists())->toBeFalse();

    // Tenant database should contain the verified initial user and their default services
    $tenant->run(function (): void {
        $user = User::query()->where('email', 'maria@clinicasonrisa.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Dra. María Gómez')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and($user->services()->count())->toBeGreaterThan(0);
    });
});

it('isolates data between multiple tenant databases', function (): void {
    $action = app(CreateTenantAction::class);

    $tenantA = $action->handle(CreateTenantData::fromArray([
        'id' => 'clinica-norte',
        'name' => 'Clínica Norte',
        'tenancy_db_name' => 'test_tenant_norte.sqlite',
        'domain' => 'norte.localhost',
        'create_initial_user' => true,
        'admin_name' => 'Dr. Norte',
        'admin_email' => 'admin@norte.com',
        'admin_password' => 'password123',
    ]));

    $tenantB = $action->handle(CreateTenantData::fromArray([
        'id' => 'clinica-sur',
        'name' => 'Clínica Sur',
        'tenancy_db_name' => 'test_tenant_sur.sqlite',
        'domain' => 'sur.localhost',
        'create_initial_user' => false,
    ]));

    $tenantA->run(function (): void {
        $userA = User::query()->firstOrFail();
        $serviceA = Service::factory()->for($userA)->create(['name' => 'Ortodoncia Exclusiva Norte']);
        CommissionRecord::factory()->for($userA)->for($serviceA)->create([
            'price' => 3000,
            'commission' => 20,
        ]);
    });

    $tenantB->run(function (): void {
        expect(User::query()->count())->toBe(0)
            ->and(Service::query()->withoutGlobalScopes()->count())->toBe(0)
            ->and(CommissionRecord::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    $tenantA->run(function (): void {
        expect(User::query()->count())->toBe(1)
            ->and(Service::query()->withoutGlobalScopes()->where('name', 'Ortodoncia Exclusiva Norte')->exists())->toBeTrue()
            ->and(CommissionRecord::query()->withoutGlobalScopes()->count())->toBe(1);
    });
});
