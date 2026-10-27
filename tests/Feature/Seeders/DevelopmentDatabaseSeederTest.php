<?php

use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\CommissionRecord;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DevelopmentDatabaseSeeder;
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

it('seeds full development dataset for central and tenant context', function (): void {
    $this->seed(DevelopmentDatabaseSeeder::class);

    // 1. Central assertions
    $superadmin = User::query()->where('email', 'superadmin@email.com')->first();
    expect($superadmin)->not->toBeNull()
        ->and($superadmin->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();

    $client = Client::query()->where('email', 'corporativo@sonrisa.com')->first();
    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('Inversiones Dentales Sonrisa SRL');

    $tenant = Tenant::query()->find('sonrisa');
    expect($tenant)->not->toBeNull()
        ->and($tenant->domains()->pluck('domain')->all())->toContain('sonrisa.localhost', 'sonrisa');

    // 2. Tenant assertions
    $tenant->run(function (): void {
        expect(Clinic::query()->count())->toBe(1);
        expect(Branch::query()->count())->toBe(2);
        expect(Cubicle::query()->count())->toBeGreaterThanOrEqual(6);

        // Staff members
        $admin = User::query()->where('email', 'admin@sonrisa.com')->first();
        expect($admin)->not->toBeNull()
            ->and($admin->hasRole(RoleEnum::Admin->value))->toBeTrue()
            ->and($admin->services()->count())->toBeGreaterThanOrEqual(4);

        $dentist = User::query()->where('email', 'dr.ramirez@sonrisa.com')->first();
        expect($dentist)->not->toBeNull()
            ->and($dentist->hasRole(RoleEnum::Dentist->value))->toBeTrue()
            ->and($dentist->services()->count())->toBeGreaterThanOrEqual(4);

        $receptionist = User::query()->where('email', 'recepcion@sonrisa.com')->first();
        expect($receptionist)->not->toBeNull()
            ->and($receptionist->hasRole(RoleEnum::Receptionist->value))->toBeTrue();

        $assistant = User::query()->where('email', 'asistente@sonrisa.com')->first();
        expect($assistant)->not->toBeNull()
            ->and($assistant->hasRole(RoleEnum::DentalAssistant->value))->toBeTrue();

        // Patients & Commission records
        expect(Patient::query()->count())->toBe(8);
        expect(CommissionRecord::withoutGlobalScopes()->count())->toBe(8);
    });
});

it('can be executed multiple times without duplicating or failing (idempotency)', function (): void {
    $this->seed(DevelopmentDatabaseSeeder::class);
    $this->seed(DevelopmentDatabaseSeeder::class);

    expect(Tenant::query()->where('id', 'sonrisa')->count())->toBe(1);
    expect(Client::query()->where('email', 'corporativo@sonrisa.com')->count())->toBe(1);

    $tenant = Tenant::query()->find('sonrisa');
    $tenant->run(function (): void {
        expect(Clinic::query()->count())->toBe(1);
        expect(Branch::query()->count())->toBe(2);
        expect(Patient::query()->count())->toBe(8);
        expect(User::query()->where('email', 'admin@sonrisa.com')->count())->toBe(1);
        expect(User::query()->where('email', 'dr.ramirez@sonrisa.com')->count())->toBe(1);
    });
});
