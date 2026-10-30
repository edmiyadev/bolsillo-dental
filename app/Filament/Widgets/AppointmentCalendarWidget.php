<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\RoleEnum;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AppointmentCalendarWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.appointment-calendar-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $deferredFilters = [];

    /**
     * Backward-compatible mirror for filters.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $branchId = request()->query('branch_id');

        if (filled($branchId)) {
            $this->filters['branch_id'] = (int) $branchId;
            $this->deferredFilters['branch_id'] = (int) $branchId;
            $this->data['branch_id'] = (int) $branchId;
        }

        $this->filtersForm->fill($this->deferredFilters ?? []);
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_appointments') ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('deferredFilters')
            ->components([
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
                    ->preload(),

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
                    ->preload(),

                Select::make('status')
                    ->label(__('Status'))
                    ->placeholder(__('Show All'))
                    ->prefixIcon(Heroicon::OutlinedClock)
                    ->options(AppointmentStatus::class)
                    ->searchable(),
            ]);
    }

    /**
     * @return array<string>
     */
    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function getFiltersTriggerAction(): Action
    {
        return Action::make('filter')
            ->label(__('filament-tables::table.actions.filter.label'))
            ->hiddenLabel()
            ->icon(Heroicon::OutlinedFunnel)
            ->color('gray')
            ->badge(fn (): ?int => ($count = count(array_filter($this->filters ?? []))) > 0 ? $count : 0)
            ->livewireClickHandlerEnabled(false);
    }

    public function filterAction(): Action
    {
        return $this->getFiltersTriggerAction();
    }

    public function filterAppointmentsAction(): Action
    {
        return $this->getFiltersTriggerAction();
    }

    public function getFiltersApplyAction(): Action
    {
        return Action::make('applyFilters')
            ->label(__('filament-tables::table.filters.actions.apply.label'))
            ->action('applyFilters')
            ->button()
            ->close();
    }

    public function getFiltersResetAction(): Action
    {
        return Action::make('resetFilters')
            ->label(__('filament-tables::table.filters.actions.reset.label'))
            ->action('resetFiltersForm')
            ->color('danger')
            ->button()
            ->close();
    }

    public function applyFilters(): void
    {
        $this->filters = array_filter($this->deferredFilters ?? [], fn ($value): bool => filled($value));
        $this->data = $this->filters;
        $this->dispatch('calendar-filters-updated');
    }

    public function resetFiltersForm(): void
    {
        $this->deferredFilters = [];
        $this->filters = [];
        $this->data = [];
        $this->filtersForm->fill([]);
        $this->dispatch('calendar-filters-updated');
    }

    public function applyFiltersAction(): Action
    {
        return $this->getFiltersApplyAction();
    }

    public function resetFiltersAction(): Action
    {
        return $this->getFiltersResetAction();
    }

    public function createAppointmentAction(): CreateAction
    {
        return CreateAction::make('createAppointment')
            ->model(Appointment::class)
            ->schema(fn (Schema $schema): Schema => AppointmentForm::configure($schema))
            ->modalWidth(Width::FiveExtraLarge)
            ->fillForm(function (Action $action): array {
                $arguments = $action->getArguments();
                $data = [];

                if (! empty($arguments['starts_at'])) {
                    $data['starts_at'] = $arguments['starts_at'];
                }

                if (! empty($arguments['branch_id'])) {
                    $data['branch_id'] = (int) $arguments['branch_id'];
                } elseif (! empty($this->filters['branch_id'])) {
                    $data['branch_id'] = (int) $this->filters['branch_id'];
                }

                return $data;
            })
            ->after(function (): void {
                $this->dispatch('calendar-filters-updated');
            })
            ->authorize(fn (): bool => auth()->user()?->can('create_appointments') ?? false);
    }

    public function editAppointmentAction(): EditAction
    {
        return EditAction::make('editAppointment')
            ->model(Appointment::class)
            ->record(fn (Action $action): ?Appointment => filled($id = $action->getArguments()['record'] ?? null)
                ? Appointment::with(['patient', 'dentist', 'cubicle', 'branch', 'service'])->find($id)
                : null)
            ->schema(fn (Schema $schema): Schema => AppointmentForm::configure($schema))
            ->modalWidth(Width::FiveExtraLarge)
            ->after(function (): void {
                $this->dispatch('calendar-filters-updated');
            })
            ->authorize(fn (): bool => auth()->user()?->can('update_appointments') ?? false);
    }

    public function viewAppointmentAction(): ViewAction
    {
        return ViewAction::make('viewAppointment')
            ->model(Appointment::class)
            ->record(fn (Action $action): ?Appointment => filled($id = $action->getArguments()['record'] ?? null)
                ? Appointment::with(['patient', 'dentist', 'cubicle', 'branch', 'service'])->find($id)
                : null)
            ->schema(fn (Schema $schema): Schema => AppointmentInfolist::configure($schema))
            ->modalWidth(Width::FiveExtraLarge)
            ->authorize(fn (): bool => auth()->user()?->can('view_appointments') ?? false);
    }

    public function getCanCreateProperty(): bool
    {
        return auth()->user()?->can('create_appointments') ?? false;
    }

    public function getCanUpdateProperty(): bool
    {
        return auth()->user()?->can('update_appointments') ?? false;
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

        $branchId ??= ! empty($this->filters['branch_id'])
            ? (int) $this->filters['branch_id']
            : (! empty($this->data['branch_id']) ? (int) $this->data['branch_id'] : null);
        $dentistId ??= ! empty($this->filters['dentist_id'])
            ? (int) $this->filters['dentist_id']
            : (! empty($this->data['dentist_id']) ? (int) $this->data['dentist_id'] : null);
        $status ??= ! empty($this->filters['status'])
            ? (string) $this->filters['status']
            : (! empty($this->data['status']) ? (string) $this->data['status'] : null);

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

                return [
                    'id' => (string) $appointment->id,
                    'title' => $reason !== '' ? "{$patientName} — {$reason}" : $patientName,
                    'start' => $appointment->starts_at?->format('Y-m-d\TH:i:s'),
                    'end' => $appointment->ends_at?->format('Y-m-d\TH:i:s'),
                    'url' => $canUpdate
                        ? AppointmentResource::getUrl('edit', ['record' => $appointment->id])
                        : AppointmentResource::getUrl('view', ['record' => $appointment->id]),
                    'color' => $this->resolveStatusColor($appointment->status),
                    'extendedProps' => [
                        'patient' => $patientName,
                        'dentist' => (string) ($appointment->dentist?->name ?? ''),
                        'cubicle' => (string) ($appointment->cubicle?->name ?? ''),
                        'branch' => (string) ($appointment->branch?->name ?? ''),
                        'reason' => $reason,
                        'status' => $appointment->status?->getLabel() ?? '',
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
