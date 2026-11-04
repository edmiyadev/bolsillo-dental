<?php

namespace Tests\Feature\Authorization;

use App\Enums\RoleEnum;
use App\Filament\Clusters\Settings\Pages\EditClinicProfile;
use App\Filament\Clusters\Settings\Resources\Branches\BranchResource;
use App\Filament\Clusters\Settings\Resources\Cubicles\CubicleResource;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\CommissionRecord;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);

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

    $this->dentist = User::factory()->create([
        'name' => 'Dr. Odontólogo',
        'email' => 'dentist@bolsillodental.test',
    ]);
    $this->dentist->assignRole(RoleEnum::Dentist->value);
});

describe('Dentist allowed modules', function (): void {
    it('allows dentist to access patient resource and permissions', function (): void {
        $this->actingAs($this->dentist);

        expect(Gate::forUser($this->dentist)->allows('viewAny', Patient::class))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('create', Patient::class))->toBeTrue()
            ->and(PatientResource::canAccess())->toBeTrue();

        $patient = Patient::factory()->create();

        expect(Gate::forUser($this->dentist)->allows('view', $patient))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('update', $patient))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('delete', $patient))->toBeFalse();

        $this->get(PatientResource::getUrl('index'))
            ->assertOk();
    });

    it('allows dentist to access service resource and own service permissions', function (): void {
        $this->actingAs($this->dentist);

        expect(Gate::forUser($this->dentist)->allows('viewAny', Service::class))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('create', Service::class))->toBeTrue()
            ->and(ServiceResource::canAccess())->toBeTrue();

        $ownService = Service::factory()->for($this->dentist)->create();
        $otherUser = User::factory()->create();
        $otherService = Service::factory()->for($otherUser)->create();

        expect(Gate::forUser($this->dentist)->allows('view', $ownService))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('update', $ownService))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('delete', $ownService))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('view', $otherService))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $otherService))->toBeFalse();

        $this->get(ServiceResource::getUrl('index'))
            ->assertOk();
    });

    it('allows dentist to access commission record resource and own record permissions', function (): void {
        $this->actingAs($this->dentist);

        expect(Gate::forUser($this->dentist)->allows('viewAny', CommissionRecord::class))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('create', CommissionRecord::class))->toBeTrue()
            ->and(CommissionRecordResource::canAccess())->toBeTrue();

        $service = Service::factory()->for($this->dentist)->create();
        $ownRecord = CommissionRecord::factory()->for($this->dentist)->create(['service_id' => $service->id]);

        expect(Gate::forUser($this->dentist)->allows('view', $ownRecord))->toBeTrue()
            ->and(Gate::forUser($this->dentist)->allows('update', $ownRecord))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('delete', $ownRecord))->toBeFalse();

        $this->get(CommissionRecordResource::getUrl('index'))
            ->assertOk();
    });
});

describe('Dentist forbidden modules', function (): void {
    it('denies dentist access to branch resource and policy', function (): void {
        $this->actingAs($this->dentist);

        expect(Gate::forUser($this->dentist)->allows('viewAny', Branch::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('create', Branch::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('view', $this->branch))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $this->branch))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('delete', $this->branch))->toBeFalse()
            ->and(BranchResource::canAccess())->toBeFalse();

        $this->get(BranchResource::getUrl('index'))
            ->assertForbidden();
    });

    it('denies dentist access to cubicle resource and policy', function (): void {
        $this->actingAs($this->dentist);

        $cubicle = Cubicle::factory()->create(['branch_id' => $this->branch->id]);

        expect(Gate::forUser($this->dentist)->allows('viewAny', Cubicle::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('create', Cubicle::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('view', $cubicle))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $cubicle))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('delete', $cubicle))->toBeFalse()
            ->and(CubicleResource::canAccess())->toBeFalse();

        $this->get(CubicleResource::getUrl('index'))
            ->assertForbidden();
    });

    it('denies dentist access to clinic settings profile and policy', function (): void {
        $this->actingAs($this->dentist);

        expect(Gate::forUser($this->dentist)->allows('viewAny', Clinic::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $this->clinic))->toBeFalse()
            ->and(EditClinicProfile::canAccess())->toBeFalse();

        $this->get(EditClinicProfile::getUrl())
            ->assertForbidden();
    });

    it('denies dentist access to settings cluster and hides navigation', function (): void {
        $this->actingAs($this->dentist);

        expect(SettingsCluster::canAccess())->toBeFalse()
            ->and(SettingsCluster::shouldRegisterNavigation())->toBeFalse();

        $this->get(SettingsCluster::getUrl())
            ->assertForbidden();
    });

    it('denies dentist access to collaborators/users resource and policy', function (): void {
        $this->actingAs($this->dentist);

        $otherUser = User::factory()->create();

        expect(Gate::forUser($this->dentist)->allows('viewAny', User::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('create', User::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('view', $otherUser))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $otherUser))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('delete', $otherUser))->toBeFalse()
            ->and(UserResource::canAccess())->toBeFalse();

        $this->get(UserResource::getUrl('index'))
            ->assertForbidden();
    });

    it('denies dentist access to roles resource and policy', function (): void {
        $this->actingAs($this->dentist);

        $role = Role::findByName(RoleEnum::Dentist->value, 'web');

        expect(Gate::forUser($this->dentist)->allows('viewAny', Role::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('create', Role::class))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('view', $role))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('update', $role))->toBeFalse()
            ->and(Gate::forUser($this->dentist)->allows('delete', $role))->toBeFalse()
            ->and(RoleResource::canAccess())->toBeFalse();

        $this->get(RoleResource::getUrl('index'))
            ->assertForbidden();
    });
});
