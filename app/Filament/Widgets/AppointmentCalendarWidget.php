<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\RoleEnum;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AppointmentCalendarWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.appointment-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'branch_id' => request()->query('branch_id'),
        ]);
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_appointments') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make()
                    ->columns(3)
                    ->schema([
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->placeholder(__('Show All'))
                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                            ->options(fn (): array => Branch::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live(),

                        Select::make('dentist_id')
                            ->label(__('Dentist'))
                            ->placeholder(__('Show All'))
                            ->prefixIcon(Heroicon::OutlinedUser)
                            ->options(fn (): array => User::query()
                                ->role(RoleEnum::Dentist->value)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live(),

                        Select::make('status')
                            ->label(__('Status'))
                            ->placeholder(__('Show All'))
                            ->prefixIcon(Heroicon::OutlinedClock)
                            ->options(AppointmentStatus::class)
                            ->searchable()
                            ->live(),
                    ]),
            ]);
    }

    public function updatedData(): void
    {
        $this->dispatch('calendar-filters-updated');
    }

    public function getCanCreateProperty(): bool
    {
        return auth()->user()?->can('create_appointments') ?? false;
    }

    public function getCreateUrlProperty(): string
    {
        return AppointmentResource::getUrl('create');
    }

    /**
     * @return array<string, array{label: string, color: string}>
     */
    public function getStatusLegendProperty(): array
    {
        return collect(AppointmentStatus::cases())
            ->mapWithKeys(fn (AppointmentStatus $status): array => [
                $status->value => [
                    'label' => $status->getLabel(),
                    'color' => $this->resolveStatusColor($status),
                ],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchEvents(
        string $start,
        string $end,
        ?int $branchId = null,
        ?int $dentistId = null,
        ?string $status = null,
    ): array {
        if (! static::canView()) {
            return [];
        }

        $branchId ??= ! empty($this->data['branch_id']) ? (int) $this->data['branch_id'] : null;
        $dentistId ??= ! empty($this->data['dentist_id']) ? (int) $this->data['dentist_id'] : null;
        $status ??= ! empty($this->data['status']) ? (string) $this->data['status'] : null;
        $canUpdate = auth()->user()?->can('update_appointments') ?? false;

        return Appointment::query()
            ->with(['patient', 'dentist', 'cubicle', 'branch', 'service'])
            ->where('starts_at', '<', Carbon::parse($end))
            ->where('ends_at', '>', Carbon::parse($start))
            ->when($branchId, fn (Builder $query, int $id): Builder => $query->where('branch_id', $id))
            ->when($dentistId, fn (Builder $query, int $id): Builder => $query->where('dentist_id', $id))
            ->when($status, fn (Builder $query, string $state): Builder => $query->where('status', $state))
            ->orderBy('starts_at')
            ->get()
            ->map(function (Appointment $appointment) use ($canUpdate): array {
                $patientName = trim("{$appointment->patient?->first_name} {$appointment->patient?->last_name}");
                $reason = $appointment->reasonLabel();
                $color = $this->resolveStatusColor($appointment->status);

                return [
                    'id' => (string) $appointment->id,
                    'title' => $reason !== '' ? "{$patientName} — {$reason}" : $patientName,
                    'start' => $appointment->starts_at?->toIso8601String(),
                    'end' => $appointment->ends_at?->toIso8601String(),
                    'url' => $canUpdate
                        ? AppointmentResource::getUrl('edit', ['record' => $appointment->id])
                        : AppointmentResource::getUrl('view', ['record' => $appointment->id]),
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'patient' => $patientName,
                        'dentist' => (string) ($appointment->dentist?->name ?? ''),
                        'cubicle' => (string) ($appointment->cubicle?->name ?? ''),
                        'branch' => (string) ($appointment->branch?->name ?? ''),
                        'reason' => $reason,
                        'specialty' => $appointment->specialty?->getLabel() ?? '',
                        'status' => $appointment->status?->getLabel() ?? '',
                        'statusValue' => $appointment->status?->value ?? '',
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveStatusColor(?AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Scheduled => '#0284c7',
            AppointmentStatus::Confirmed => '#0891b2',
            AppointmentStatus::InProgress => '#d97706',
            AppointmentStatus::Completed => '#16a34a',
            AppointmentStatus::Cancelled => '#dc2626',
            AppointmentStatus::NoShow => '#6b7280',
            default => '#0284c7',
        };
    }
}
