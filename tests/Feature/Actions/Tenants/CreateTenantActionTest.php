<?php

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Models\Client;
use App\Models\Clinic;
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

it('creates a tenant linked to a client, provisions the clinic record in the tenant database, and sets up the initial user', function (): void {
    $client = Client::factory()->create([
        'name' => 'Inversiones Sonrisa SRL',
        'email' => 'corporativo@sonrisa.com',
    ]);

    $data = CreateTenantData::fromArray([
        'id' => 'clinica-sonrisa',
        'client_id' => $client->id,
        'name' => 'Clínica Dental Sonrisa',
        'email' => 'contacto@clinicasonrisa.com',
        'phone' => '809-555-1234',
        'rnc' => '131234567',
        'address' => 'Calle El Sol #10, Santiago',
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
        ->and($tenant->client_id)->toBe($client->id)
        ->and($tenant->client->is($client))->toBeTrue()
        ->and($tenant->name)->toBe('Clínica Dental Sonrisa')
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->databaseName())->toBe('test_tenant_clinica_sonrisa.sqlite')
        ->and($tenant->domains()->pluck('domain')->all())->toBe(['clinica-sonrisa.localhost'])
        ->and(file_exists(database_path('test_tenant_clinica_sonrisa.sqlite')))->toBeTrue();

    // Central database should not contain the tenant's user
    expect(User::query()->where('email', 'maria@clinicasonrisa.com')->exists())->toBeFalse();

    // Tenant database should contain the provisioned Clinic record, verified initial user, and default services
    $tenant->run(function (): void {
        $clinic = Clinic::query()->first();

        expect($clinic)->not->toBeNull()
            ->and($clinic->name)->toBe('Clínica Dental Sonrisa')
            ->and($clinic->email)->toBe('contacto@clinicasonrisa.com')
            ->and($clinic->phone)->toBe('809-555-1234')
            ->and($clinic->rnc)->toBe('131234567')
            ->and($clinic->address)->toBe('Calle El Sol #10, Santiago')
            ->and($clinic->branches()->count())->toBe(1);

        $mainBranch = $clinic->mainBranch;
        expect($mainBranch)->not->toBeNull()
            ->and($mainBranch->name)->toBe('Sede Principal')
            ->and($mainBranch->code)->toBe('MAIN')
            ->and($mainBranch->phone)->toBe('809-555-1234')
            ->and($mainBranch->address)->toBe('Calle El Sol #10, Santiago')
            ->and($mainBranch->is_main)->toBeTrue()
            ->and($mainBranch->is_active)->toBeTrue();

        $user = User::query()->where('email', 'maria@clinicasonrisa.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Dra. María Gómez')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and($user->services()->count())->toBe(4);
    });
});

it('isolates clinic, service, and commission data between multiple tenant databases', function (): void {
    $action = app(CreateTenantAction::class);
    $client = Client::factory()->create();

    $tenantA = $action->handle(CreateTenantData::fromArray([
        'id' => 'clinica-norte',
        'client_id' => $client->id,
        'name' => 'Clínica Norte',
        'rnc' => '101000111',
        'tenancy_db_name' => 'test_tenant_norte.sqlite',
        'domain' => 'norte.localhost',
        'create_initial_user' => true,
        'admin_name' => 'Dr. Norte',
        'admin_email' => 'admin@norte.com',
        'admin_password' => 'password123',
    ]));

    $tenantB = $action->handle(CreateTenantData::fromArray([
        'id' => 'clinica-sur',
        'client_id' => $client->id,
        'name' => 'Clínica Sur',
        'rnc' => '202000222',
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
        expect(Clinic::query()->count())->toBe(1)
            ->and(Clinic::query()->first()?->name)->toBe('Clínica Sur')
            ->and(Clinic::query()->first()?->rnc)->toBe('202000222')
            ->and(User::query()->count())->toBe(0)
            ->and(Service::query()->withoutGlobalScopes()->count())->toBe(0)
            ->and(CommissionRecord::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    $tenantA->run(function (): void {
        expect(Clinic::query()->count())->toBe(1)
            ->and(Clinic::query()->first()?->name)->toBe('Clínica Norte')
            ->and(User::query()->count())->toBe(1)
            ->and(Service::query()->withoutGlobalScopes()->where('name', 'Ortodoncia Exclusiva Norte')->exists())->toBeTrue()
            ->and(CommissionRecord::query()->withoutGlobalScopes()->count())->toBe(1);
    });
});
