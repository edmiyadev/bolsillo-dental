<?php

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\CubicleStatus;
use App\Enums\IdentificationType;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\CommissionRecord;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\assertNotSoftDeleted;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Patient SoftDeletes', function (): void {
    it('soft deletes, scopes out by default, and restores a patient', function (): void {
        $patient = Patient::factory()->create([
            'first_name' => 'María',
            'last_name' => 'Rodríguez',
            'identification_number' => '40212345678',
        ]);

        $patient->delete();

        assertSoftDeleted($patient);
        expect(Patient::count())->toBe(0)
            ->and(Patient::withTrashed()->count())->toBe(1)
            ->and(Patient::onlyTrashed()->count())->toBe(1)
            ->and(Patient::find($patient->id))->toBeNull()
            ->and(Patient::withTrashed()->find($patient->id)?->id)->toBe($patient->id);

        $patient->restore();

        assertNotSoftDeleted($patient);
        expect(Patient::count())->toBe(1)
            ->and(Patient::find($patient->id)?->first_name)->toBe('María');
    });

    it('force deletes a patient permanently from database', function (): void {
        $patient = Patient::factory()->create();

        $patient->forceDelete();

        assertModelMissing($patient);
        expect(Patient::withTrashed()->count())->toBe(0);
    });
});

describe('Branch SoftDeletes', function (): void {
    it('soft deletes and restores a branch when multiple branches exist', function (): void {
        $clinic = Clinic::factory()->create();
        $mainBranch = Branch::factory()->main()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Sede Central',
        ]);
        $secondaryBranch = Branch::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Sucursal Bella Vista',
            'code' => 'BV-01',
        ]);

        $secondaryBranch->delete();

        assertSoftDeleted($secondaryBranch);
        expect(Branch::count())->toBe(1)
            ->and(Branch::where('id', $secondaryBranch->id)->exists())->toBeFalse()
            ->and(Branch::withTrashed()->where('id', $secondaryBranch->id)->exists())->toBeTrue()
            ->and($clinic->fresh()->branches->pluck('id'))->toContain($mainBranch->id)
            ->and($clinic->fresh()->branches->pluck('id'))->not->toContain($secondaryBranch->id);

        $secondaryBranch->restore();

        assertNotSoftDeleted($secondaryBranch);
        expect(Branch::count())->toBe(2)
            ->and($clinic->fresh()->branches->pluck('id'))->toContain($secondaryBranch->id);
    });
});

describe('Cubicle SoftDeletes', function (): void {
    it('soft deletes, scopes out, and restores a cubicle while maintaining branch relationship', function (): void {
        $clinic = Clinic::factory()->create();
        $branch = Branch::factory()->main()->create(['clinic_id' => $clinic->id]);
        $cubicle = Cubicle::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Sillón Ortodoncia',
            'status' => CubicleStatus::Active,
        ]);

        $cubicle->delete();

        assertSoftDeleted($cubicle);
        expect(Cubicle::count())->toBe(0)
            ->and(Cubicle::withTrashed()->count())->toBe(1)
            ->and(Cubicle::onlyTrashed()->first()?->id)->toBe($cubicle->id)
            ->and($branch->fresh()->cubicles)->toHaveCount(0);

        // Branch relation resolves even if branch was soft deleted
        expect($cubicle->branch->id)->toBe($branch->id);

        $cubicle->restore();

        assertNotSoftDeleted($cubicle);
        expect(Cubicle::count())->toBe(1)
            ->and($branch->fresh()->cubicles)->toHaveCount(1);
    });
});

describe('Service SoftDeletes', function (): void {
    it('soft deletes a service without breaking existing commission records', function (): void {
        $service = Service::factory()->for($this->user)->create([
            'name' => 'Endodoncia Unirradicular',
            'default_price' => 5000.00,
            'default_commission' => 30.00,
            'commission_type' => CommissionType::Percentage,
        ]);

        $record = CommissionRecord::factory()->for($this->user)->create([
            'service_id' => $service->id,
            'price' => 5000.00,
            'commission' => 30.00,
            'commission_type' => CommissionType::Percentage,
            'status' => CommissionStatus::Pending,
            'worked_at' => now()->toDateString(),
        ]);

        // Soft delete the service
        $service->delete();

        assertSoftDeleted($service);
        expect(Service::count())->toBe(0)
            ->and(Service::withTrashed()->count())->toBe(1);

        // Commission record still exists and its service relationship resolves via withTrashed()
        $freshRecord = $record->fresh();
        expect($freshRecord)->not->toBeNull()
            ->and($freshRecord->service)->not->toBeNull()
            ->and($freshRecord->service->id)->toBe($service->id)
            ->and($freshRecord->service->name)->toBe('Endodoncia Unirradicular');

        // Service can be restored
        $service->restore();
        assertNotSoftDeleted($service);
        expect(Service::count())->toBe(1);
    });
});

describe('CommissionRecord SoftDeletes', function (): void {
    it('soft deletes, scopes out, and restores a commission record for accounting traceability', function (): void {
        $service = Service::factory()->for($this->user)->create();
        $record = CommissionRecord::factory()->for($this->user)->create([
            'service_id' => $service->id,
            'price' => 1500.00,
            'commission' => 20.00,
            'commission_type' => CommissionType::Percentage,
            'status' => CommissionStatus::Paid,
            'worked_at' => now()->subDays(5)->toDateString(),
        ]);

        $record->delete();

        assertSoftDeleted($record);
        expect(CommissionRecord::count())->toBe(0)
            ->and(CommissionRecord::withTrashed()->count())->toBe(1)
            ->and(CommissionRecord::onlyTrashed()->first()?->id)->toBe($record->id);

        $record->restore();

        assertNotSoftDeleted($record);
        expect(CommissionRecord::count())->toBe(1)
            ->and(CommissionRecord::first()?->status)->toBe(CommissionStatus::Paid);
    });
});
