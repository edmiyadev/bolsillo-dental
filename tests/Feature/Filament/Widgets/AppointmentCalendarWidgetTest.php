<?php

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Pages\CalendarAppointments;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed([RoleSeeder::class, PermissionSeeder::class]);

    $this->clinic = Clinic::factory()->create();
    $this->branch = Branch::factory()->main()->create([
        'clinic_id' => $this->clinic->id,
        'opening_hours' => DayOfWeek::defaultSchedule(),
    ]);

    $this->dentist = User::factory()->create(['name' => 'Dr. Ramírez']);
    $this->dentist->assignRole(RoleEnum::Dentist->value);

    $this->cubicle = Cubicle::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Cubículo 1',
        'status' => CubicleStatus::Active,
        'specialty_types' => [DentalSpecialty::General->value],
    ]);

    $this->patient = Patient::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Soto',
    ]);
});

it('renders the calendar page via action from list page and includes the custom calendar widget', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Receptionist->value);
    $this->actingAs($user);

    Livewire::test(ListAppointments::class)
        ->assertActionVisible('calendar')
        ->assertActionHasUrl('calendar', AppointmentResource::getUrl('calendar'));

    Livewire::test(CalendarAppointments::class)
        ->assertOk()
        ->assertActionVisible('list')
        ->assertSeeLivewire(AppointmentCalendarWidget::class);
});

it('fetches formatted calendar events filtered by date range, branch, dentist, and status', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleEnum::Receptionist->value);
    $this->actingAs($user);

    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'is_custom_reason' => true,
        'custom_reason' => 'Revisión General',
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-03 10:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed,
    ]);

    // Out-of-range appointment (next week)
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'is_custom_reason' => true,
        'custom_reason' => 'Cita Fuera de Rango',
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-10 10:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed,
    ]);

    // Same date range, but different status
    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'is_custom_reason' => true,
        'custom_reason' => 'Cita Cancelada',
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-04 11:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Cancelled,
    ]);

    // Same date range and status, but different branch and dentist
    $otherBranch = Branch::factory()->create(['clinic_id' => $this->clinic->id]);
    $otherDentist = User::factory()->create(['name' => 'Dra. Gómez']);
    $otherDentist->assignRole(RoleEnum::Dentist->value);

    Appointment::factory()->create([
        'branch_id' => $otherBranch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $otherDentist->id,
        'cubicle_id' => $this->cubicle->id,
        'is_custom_reason' => true,
        'custom_reason' => 'Otra Sucursal',
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-05 09:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed,
    ]);

    $component = Livewire::test(AppointmentCalendarWidget::class);

    $events = $component->instance()->fetchEvents(
        '2026-11-01T00:00:00',
        '2026-11-07T23:59:59',
        $this->branch->id,
        $this->dentist->id,
        AppointmentStatus::Confirmed->value,
    );

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe((string) $appointment->id)
        ->and($events[0]['title'])->toContain('Ana Soto')
        ->and($events[0]['extendedProps']['dentist'])->toBe('Dr. Ramírez')
        ->and($events[0]['extendedProps']['cubicle'])->toBe('Cubículo 1');
});

it('returns an empty array when an unauthorized user attempts to fetch calendar events', function (): void {
    $unauthorizedUser = User::factory()->create();
    $this->actingAs($unauthorizedUser);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'dentist_id' => $this->dentist->id,
        'cubicle_id' => $this->cubicle->id,
        'is_custom_reason' => true,
        'custom_reason' => 'Revisión General',
        'specialty' => DentalSpecialty::General,
        'starts_at' => '2026-11-03 10:00:00',
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Confirmed,
    ]);

    $widget = new AppointmentCalendarWidget;

    expect(AppointmentCalendarWidget::canView())->toBeFalse()
        ->and($widget->fetchEvents('2026-11-01T00:00:00', '2026-11-07T23:59:59'))->toBe([]);
});
