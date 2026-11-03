<?php

namespace App\Filament\Widgets;

use App\Actions\Appointments\CreateAppointmentAction;
use App\Actions\Appointments\UpdateAppointmentAction;
use App\Data\Appointments\CreateAppointmentData;
use App\Data\Appointments\UpdateAppointmentData;
use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Appointments\Schemas\AppointmentFiltersForm;
use App\Filament\Resources\Appointments\Schemas\AppointmentForm;
use App\Filament\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Models\Appointment;
use App\Rules\AppointmentAvailabilityRule;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

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
            ->inlineLabel()
            ->components(AppointmentFiltersForm::make());
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
            ->hiddenLabel()
            ->icon(Heroicon::Funnel)
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
            ->modelLabel(AppointmentResource::getModelLabel())
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
            ->using(fn (array $data): Model => app(CreateAppointmentAction::class)->handle(
                CreateAppointmentData::fromArray($data)
            ))
            ->authorize(fn (): bool => auth()->user()?->can('create_appointments') ?? false);
    }

    public function editAppointmentAction(): EditAction
    {
        return EditAction::make('editAppointment')
            ->model(Appointment::class)
            ->modelLabel(AppointmentResource::getModelLabel())
            ->record(fn (Action $action): ?Appointment => filled($id = $action->getArguments()['record'] ?? null)
                ? Appointment::with(['patient', 'dentist', 'cubicle', 'branch', 'service'])->find($id)
                : null)
            ->schema(fn (Schema $schema): Schema => AppointmentForm::configure($schema))
            ->modalWidth(Width::FiveExtraLarge)
            ->after(function (): void {
                $this->dispatch('calendar-filters-updated');
            })
            ->using(fn (Appointment $record, array $data): Model => app(UpdateAppointmentAction::class)->handle(
                $record,
                UpdateAppointmentData::fromArray($data, $record)
            ))
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
    public function updateAppointmentDate(int $id, string $startsAt): bool
    {
        if (! (auth()->user()?->can('update_appointments') ?? false)) {
            Notification::make()
                ->danger()
                ->title(__('You are not authorized to update appointments.'))
                ->send();

            return false;
        }

        $appointment = Appointment::query()->find($id);

        if (! $appointment) {
            Notification::make()
                ->danger()
                ->title(__('Appointment not found.'))
                ->send();

            return false;
        }

        try {
            $newStartsAt = Carbon::parse($startsAt);
        } catch (Throwable) {
            Notification::make()
                ->danger()
                ->title(__('Invalid date and time.'))
                ->send();

            return false;
        }

        if ($newStartsAt->isPast()) {
            Notification::make()
                ->danger()
                ->title(__('Cannot schedule appointments in the past.'))
                ->send();

            return false;
        }

        $validator = Validator::make([
            'starts_at' => $newStartsAt->format('Y-m-d H:i:s'),
            'branch_id' => $appointment->branch_id,
            'dentist_id' => $appointment->dentist_id,
            'cubicle_id' => $appointment->cubicle_id,
            'duration_minutes' => $appointment->duration_minutes,
            'specialty' => $appointment->specialty?->value ?? (string) $appointment->specialty,
        ], [
            'starts_at' => [
                'required',
                'date',
                'after_or_equal:now',
                new AppointmentAvailabilityRule($appointment->id),
            ],
        ]);

        if ($validator->fails()) {
            Notification::make()
                ->danger()
                ->title($validator->errors()->first('starts_at'))
                ->send();

            return false;
        }

        app(UpdateAppointmentAction::class)->handle(
            $appointment,
            UpdateAppointmentData::fromArray(['starts_at' => $newStartsAt], $appointment),
        );

        Notification::make()
            ->success()
            ->title(__('Appointment rescheduled successfully.'))
            ->send();

        $this->dispatch('calendar-filters-updated');

        return true;
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
                $statusColorName = $appointment->status?->getColor() ?? 'primary';
                $statusColor = $this->resolveStatusColor($appointment->status);

                return [
                    'id' => (string) $appointment->id,
                    'title' => $reason !== '' ? "{$patientName} — {$reason}" : $patientName,
                    'start' => $appointment->starts_at?->format('Y-m-d\TH:i:s'),
                    'end' => $appointment->ends_at?->format('Y-m-d\TH:i:s'),
                    'url' => $canUpdate
                        ? AppointmentResource::getUrl('edit', ['record' => $appointment->id])
                        : AppointmentResource::getUrl('view', ['record' => $appointment->id]),
                    'color' => $statusColor,
                    'backgroundColor' => $statusColor,
                    'borderColor' => $statusColor,
                    'classNames' => [
                        'appointment-event',
                        "appointment-status-{$statusColorName}",
                    ],
                    'extendedProps' => [
                        'patient' => $patientName,
                        'dentist' => (string) ($appointment->dentist?->name ?? ''),
                        'cubicle' => (string) ($appointment->cubicle?->name ?? ''),
                        'branch' => (string) ($appointment->branch?->name ?? ''),
                        'reason' => $reason,
                        'status' => $appointment->status?->getLabel() ?? '',
                        'statusColor' => $statusColorName,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    protected function resolveStatusColor(?AppointmentStatus $status): string
    {
        $color = $status?->getColor() ?? 'primary';

        return match ($color) {
            'info' => 'var(--filament-color-info, var(--info-600, #0284c7))',
            'primary' => 'var(--filament-color-primary, var(--primary-600, #0891b2))',
            'warning' => 'var(--filament-color-warning, var(--warning-600, #d97706))',
            'success' => 'var(--filament-color-success, var(--success-600, #16a34a))',
            'danger' => 'var(--filament-color-danger, var(--danger-600, #dc2626))',
            'gray' => 'var(--filament-color-gray, var(--gray-500, #6b7280))',
            default => str_starts_with($color, '#') || str_starts_with($color, 'rgb') || str_starts_with($color, 'var(')
                ? $color
                : "var(--filament-color-{$color}, var(--{$color}-600, var(--primary-600, #0891b2)))",
        };
    }
}
