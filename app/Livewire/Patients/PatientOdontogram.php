<?php

namespace App\Livewire\Patients;

use App\Actions\Odontogram\CancelOdontogramEntryAction;
use App\Actions\Odontogram\CompleteOdontogramEntryAction;
use App\Actions\Odontogram\RecordOdontogramEntryAction;
use App\Enums\DentalArch;
use App\Enums\OdontogramCondition;
use App\Enums\OdontogramScope;
use App\Enums\OdontogramStatus;
use App\Enums\RoleEnum;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\OdontogramEntry;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Support\OdontogramGeometryCatalog;
use App\Support\OdontogramStateResolver;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PatientOdontogram extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Patient $record;

    public string $dentitionTab = 'permanent';

    public string $timelineMode = 'current';

    public string $interactionMode = 'mode_b';

    public string $quickCondition = 'caries';

    public function mount(Patient $record): void
    {
        $this->record = $record;
    }

    public function canUpdatePatient(): bool
    {
        return Auth::user()?->can('update_patients') ?? false;
    }

    /**
     * @return array{
     *     teeth: array<int, array<string, mixed>>,
     *     bridges: array<int, array<string, mixed>>,
     *     archEntries: Collection<int, OdontogramEntry>,
     * }
     */
    #[Computed]
    public function projection(): array
    {
        $entries = OdontogramEntry::query()
            ->forPatient($this->record->id)
            ->orderBy('id')
            ->get();

        return app(OdontogramStateResolver::class)->resolve($entries, $this->timelineMode);
    }

    /**
     * @return array<string, array{label: string, status: OdontogramStatus, scope: OdontogramScope}>
     */
    #[Computed]
    public function quickConditions(): array
    {
        return [
            'caries' => [
                'label' => __('Caries (Finding)'),
                'status' => OdontogramStatus::Finding,
                'scope' => OdontogramScope::Surface,
            ],
            'fracture' => [
                'label' => __('Fracture (Finding)'),
                'status' => OdontogramStatus::Finding,
                'scope' => OdontogramScope::Tooth,
            ],
            'missing' => [
                'label' => __('Missing tooth (Pre-existing)'),
                'status' => OdontogramStatus::PreExisting,
                'scope' => OdontogramScope::Tooth,
            ],
            'pre_resin' => [
                'label' => __('Pre-existing resin'),
                'status' => OdontogramStatus::PreExisting,
                'scope' => OdontogramScope::Surface,
            ],
            'pre_crown' => [
                'label' => __('Pre-existing crown'),
                'status' => OdontogramStatus::PreExisting,
                'scope' => OdontogramScope::Tooth,
            ],
            'pre_endodontics' => [
                'label' => __('Pre-existing endodontics'),
                'status' => OdontogramStatus::PreExisting,
                'scope' => OdontogramScope::Tooth,
            ],
            'pre_implant' => [
                'label' => __('Pre-existing implant'),
                'status' => OdontogramStatus::PreExisting,
                'scope' => OdontogramScope::Tooth,
            ],
        ];
    }

    public function recordQuickFinding(int $toothNumber, ?string $surface = null, ?string $conditionKey = null): void
    {
        if (! $this->canUpdatePatient()) {
            Notification::make()
                ->danger()
                ->title(__('You are not authorized to update patient odontograms.'))
                ->send();

            return;
        }

        $key = $conditionKey ?? $this->quickCondition;

        $mapped = match ($key) {
            'caries' => [OdontogramCondition::Caries, OdontogramStatus::Finding, OdontogramScope::Surface],
            'fracture' => [OdontogramCondition::Fracture, OdontogramStatus::Finding, OdontogramScope::Tooth],
            'missing' => [OdontogramCondition::Missing, OdontogramStatus::PreExisting, OdontogramScope::Tooth],
            'pre_resin' => [OdontogramCondition::Resin, OdontogramStatus::PreExisting, OdontogramScope::Surface],
            'pre_crown' => [OdontogramCondition::Crown, OdontogramStatus::PreExisting, OdontogramScope::Tooth],
            'pre_endodontics' => [OdontogramCondition::Endodontics, OdontogramStatus::PreExisting, OdontogramScope::Tooth],
            'pre_implant' => [OdontogramCondition::Implant, OdontogramStatus::PreExisting, OdontogramScope::Tooth],
            default => [OdontogramCondition::from($key), OdontogramStatus::Finding, OdontogramCondition::from($key)->defaultScope()],
        };

        /** @var OdontogramCondition $condition */
        /** @var OdontogramStatus $status */
        /** @var OdontogramScope $scope */
        [$condition, $status, $scope] = $mapped;

        try {
            app(RecordOdontogramEntryAction::class)->handle([
                'patient_id' => $this->record->id,
                'scope' => $scope->value,
                'tooth_number' => $toothNumber,
                'surfaces' => $scope === OdontogramScope::Surface && filled($surface) ? [$surface] : null,
                'condition_code' => $condition->value,
                'status' => $status->value,
                'dentist_id' => Auth::id(),
            ]);

            unset($this->projection);

            Notification::make()
                ->success()
                ->title(__('Odontogram record saved'))
                ->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? __('Validation error'))
                ->send();
        }
    }

    public function registerTreatmentAction(): Action
    {
        return Action::make('registerTreatment')
            ->label(__('Register treatment'))
            ->modalHeading(__('Register odontogram treatment'))
            ->modalWidth(Width::TwoExtraLarge)
            ->authorize(fn (): bool => $this->canUpdatePatient())
            ->schema(function (Action $action): array {
                $arguments = $action->getArguments();
                $scopeValue = (string) ($arguments['scope'] ?? OdontogramScope::Surface->value);
                $scope = OdontogramScope::tryFrom($scopeValue) ?? OdontogramScope::Surface;
                $toothNumber = isset($arguments['tooth_number']) ? (int) $arguments['tooth_number'] : null;
                $bridgeToTooth = isset($arguments['bridge_to_tooth']) ? (int) $arguments['bridge_to_tooth'] : null;
                $surfaces = is_array($arguments['surfaces'] ?? null) ? $arguments['surfaces'] : [];

                return [
                    Placeholder::make('selection_summary')
                        ->label(__('Anatomical selection'))
                        ->content($this->formatSelectionSummary($scope, $toothNumber, $bridgeToTooth, $surfaces)),
                    Grid::make(2)->schema([
                        Select::make('condition_code')
                            ->label(__('Condition / Treatment'))
                            ->options($this->treatmentConditionOptionsForScope($scope))
                            ->default($this->defaultConditionForScope($scope)->value)
                            ->required(),
                        Select::make('status')
                            ->label(__('Execution status'))
                            ->options([
                                OdontogramStatus::Planned->value => OdontogramStatus::Planned->getLabel(),
                                OdontogramStatus::Completed->value => OdontogramStatus::Completed->getLabel(),
                            ])
                            ->default(OdontogramStatus::Completed->value)
                            ->required(),
                    ]),
                    ...$this->sharedTreatmentFormFields(),
                ];
            })
            ->action(function (Action $action, array $data): void {
                $arguments = $action->getArguments();

                try {
                    app(RecordOdontogramEntryAction::class)->handle([
                        'patient_id' => $this->record->id,
                        'scope' => $arguments['scope'] ?? OdontogramScope::Surface->value,
                        'tooth_number' => $arguments['tooth_number'] ?? null,
                        'bridge_to_tooth' => $arguments['bridge_to_tooth'] ?? null,
                        'surfaces' => $arguments['surfaces'] ?? null,
                        'condition_code' => $data['condition_code'],
                        'status' => $data['status'],
                        'service_id' => $data['service_id'],
                        'appointment_id' => $data['appointment_id'] ?? null,
                        'branch_id' => $data['branch_id'] ?? null,
                        'dentist_id' => $data['dentist_id'] ?? null,
                        'assistant_id' => $data['assistant_id'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    unset($this->projection);
                    $this->dispatch('odontogram-selection-cleared');

                    Notification::make()
                        ->success()
                        ->title(__('Treatment registered in odontogram'))
                        ->send();
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?? __('Could not save treatment'))
                        ->send();

                    $action->halt();
                }
            });
    }

    public function registerMouthProcedureAction(): Action
    {
        return Action::make('registerMouthProcedure')
            ->label(__('+ Full Mouth / Arch Procedure'))
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('primary')
            ->size('sm')
            ->visible(fn (): bool => $this->canUpdatePatient())
            ->authorize(fn (): bool => $this->canUpdatePatient())
            ->modalHeading(__('Register full mouth or arch procedure'))
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                Grid::make(2)->schema([
                    Select::make('scope')
                        ->label(__('Scope'))
                        ->options([
                            OdontogramScope::Mouth->value => OdontogramScope::Mouth->getLabel(),
                            OdontogramScope::Arch->value => OdontogramScope::Arch->getLabel(),
                        ])
                        ->default(OdontogramScope::Mouth->value)
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === OdontogramScope::Mouth->value) {
                                $set('arch', DentalArch::Full->value);
                            } elseif ($state === OdontogramScope::Arch->value) {
                                $set('arch', DentalArch::Upper->value);
                            }
                        })
                        ->required(),
                    Select::make('arch')
                        ->label(__('Dental arch'))
                        ->options(fn (Get $get): array => $get('scope') === OdontogramScope::Arch->value
                            ? [
                                DentalArch::Upper->value => DentalArch::Upper->getLabel(),
                                DentalArch::Lower->value => DentalArch::Lower->getLabel(),
                            ]
                            : [
                                DentalArch::Full->value => DentalArch::Full->getLabel(),
                            ])
                        ->default(DentalArch::Full->value)
                        ->required(),
                    Select::make('condition_code')
                        ->label(__('Procedure'))
                        ->options([
                            OdontogramCondition::Prophylaxis->value => OdontogramCondition::Prophylaxis->getLabel(),
                        ])
                        ->default(OdontogramCondition::Prophylaxis->value)
                        ->required(),
                    Select::make('status')
                        ->label(__('Execution status'))
                        ->options([
                            OdontogramStatus::Planned->value => OdontogramStatus::Planned->getLabel(),
                            OdontogramStatus::Completed->value => OdontogramStatus::Completed->getLabel(),
                        ])
                        ->default(OdontogramStatus::Completed->value)
                        ->required(),
                ]),
                ...$this->sharedTreatmentFormFields(),
            ])
            ->action(function (Action $action, array $data): void {
                try {
                    app(RecordOdontogramEntryAction::class)->handle([
                        'patient_id' => $this->record->id,
                        'scope' => $data['scope'],
                        'arch' => $data['arch'],
                        'condition_code' => $data['condition_code'],
                        'status' => $data['status'],
                        'service_id' => $data['service_id'],
                        'appointment_id' => $data['appointment_id'] ?? null,
                        'branch_id' => $data['branch_id'] ?? null,
                        'dentist_id' => $data['dentist_id'] ?? null,
                        'assistant_id' => $data['assistant_id'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    unset($this->projection);

                    Notification::make()
                        ->success()
                        ->title(__('General procedure registered'))
                        ->send();
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?? __('Could not save procedure'))
                        ->send();

                    $action->halt();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OdontogramEntry::query()
                    ->forPatient($this->record->id)
                    ->with(['service', 'dentist', 'assistant', 'branch', 'appointment'])
            )
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('piece_or_arch')
                    ->label(__('Piece / Arch'))
                    ->state(function (OdontogramEntry $entry): string {
                        if ($entry->scope === OdontogramScope::Range) {
                            return "{$entry->tooth_number} → {$entry->bridge_to_tooth}";
                        }

                        if ($entry->tooth_number !== null) {
                            return (string) $entry->tooth_number;
                        }

                        return $entry->arch?->getLabel() ?? '-';
                    })
                    ->badge()
                    ->color('gray'),
                TextColumn::make('surfaces')
                    ->label(__('Surfaces'))
                    ->state(fn (OdontogramEntry $entry): string => ! empty($entry->surfaces) ? implode('-', $entry->surfaces) : '-')
                    ->placeholder('-'),
                TextColumn::make('condition_code')
                    ->label(__('Condition'))
                    ->badge(),
                TextColumn::make('service.name')
                    ->label(__('Service'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('dentist.name')
                    ->label(__('Dentist'))
                    ->placeholder('-'),
                TextColumn::make('assistant.name')
                    ->label(__('Dental assistant'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(OdontogramStatus::class),
                SelectFilter::make('condition_code')
                    ->label(__('Condition'))
                    ->options(OdontogramCondition::class),
            ])
            ->recordActions([
                Action::make('completeEntry')
                    ->label(__('Mark as completed'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (OdontogramEntry $entry): bool => $this->canUpdatePatient() && $entry->status === OdontogramStatus::Planned)
                    ->action(function (OdontogramEntry $entry): void {
                        app(CompleteOdontogramEntryAction::class)->handle($entry);
                        unset($this->projection);

                        Notification::make()
                            ->success()
                            ->title(__('Treatment marked as completed'))
                            ->send();
                    }),
                Action::make('cancelEntry')
                    ->label(__('Cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (OdontogramEntry $entry): bool => $this->canUpdatePatient()
                        && ! in_array($entry->status, [OdontogramStatus::Superseded, OdontogramStatus::Cancelled], true))
                    ->action(function (OdontogramEntry $entry): void {
                        app(CancelOdontogramEntryAction::class)->handle($entry);
                        unset($this->projection);

                        Notification::make()
                            ->success()
                            ->title(__('Record cancelled'))
                            ->send();
                    }),
                DeleteAction::make('deleteEntry')
                    ->visible(fn (): bool => $this->canUpdatePatient())
                    ->after(function (): void {
                        unset($this->projection);
                    }),
            ])
            ->emptyStateHeading(__('No odontogram records yet'))
            ->emptyStateDescription(__('Use Mode A for quick 1-click findings or Mode B to plan and execute treatments.'));
    }

    public function render(): View
    {
        return view('livewire.patients.patient-odontogram');
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function sharedTreatmentFormFields(): array
    {
        return [
            Select::make('service_id')
                ->label(__('Service'))
                ->options(fn (): array => Service::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->required(),
            Grid::make(2)->schema([
                Select::make('appointment_id')
                    ->label(__('Appointment'))
                    ->options(fn (): array => Appointment::withoutGlobalScopes()
                        ->where('patient_id', $this->record->id)
                        ->orderByDesc('starts_at')
                        ->get()
                        ->mapWithKeys(fn (Appointment $appointment): array => [
                            $appointment->id => "{$appointment->starts_at?->format('d/m/Y H:i')} — {$appointment->reasonLabel()}",
                        ])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        if (blank($state)) {
                            return;
                        }

                        $appointment = Appointment::withoutGlobalScopes()->find((int) $state);

                        if (! $appointment) {
                            return;
                        }

                        if (blank($get('branch_id')) && $appointment->branch_id !== null) {
                            $set('branch_id', $appointment->branch_id);
                        }

                        if (blank($get('dentist_id')) && $appointment->dentist_id !== null) {
                            $set('dentist_id', $appointment->dentist_id);
                        }
                    }),
                Select::make('branch_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                Select::make('dentist_id')
                    ->label(__('Dentist'))
                    ->options(fn (): array => $this->dentistOptions())
                    ->default(fn (): ?int => Auth::id())
                    ->searchable()
                    ->preload(),
                Select::make('assistant_id')
                    ->label(__('Dental assistant'))
                    ->options(fn (): array => $this->assistantOptions())
                    ->searchable()
                    ->preload(),
            ]),
            Textarea::make('notes')
                ->label(__('Clinical observations'))
                ->rows(3),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function dentistOptions(): array
    {
        $dentists = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                RoleEnum::Dentist->value,
                RoleEnum::Admin->value,
                RoleEnum::SuperAdmin->value,
            ]))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($dentists !== []) {
            return $dentists;
        }

        return User::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    protected function assistantOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RoleEnum::DentalAssistant->value))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function treatmentConditionOptionsForScope(OdontogramScope $scope): array
    {
        return collect(OdontogramCondition::cases())
            ->filter(fn (OdontogramCondition $condition): bool => $condition->isTreatmentCondition() && $condition->supportsScope($scope))
            ->mapWithKeys(fn (OdontogramCondition $condition): array => [$condition->value => $condition->getLabel()])
            ->all();
    }

    protected function defaultConditionForScope(OdontogramScope $scope): OdontogramCondition
    {
        return match ($scope) {
            OdontogramScope::Surface => OdontogramCondition::Resin,
            OdontogramScope::Tooth => OdontogramCondition::Crown,
            OdontogramScope::Range => OdontogramCondition::Bridge,
            OdontogramScope::Arch, OdontogramScope::Mouth => OdontogramCondition::Prophylaxis,
        };
    }

    /**
     * @param  array<int, string>  $surfaces
     */
    protected function formatSelectionSummary(OdontogramScope $scope, ?int $toothNumber, ?int $bridgeToTooth, array $surfaces): string
    {
        if ($scope === OdontogramScope::Range) {
            return __('Fixed bridge: tooth :from to tooth :to', [
                'from' => (string) $toothNumber,
                'to' => (string) $bridgeToTooth,
            ]);
        }

        if ($scope === OdontogramScope::Tooth) {
            return __('Full tooth :tooth', ['tooth' => (string) $toothNumber]);
        }

        if ($scope === OdontogramScope::Surface && $toothNumber !== null) {
            $normalized = OdontogramGeometryCatalog::normalizeSurfaces($toothNumber, $surfaces);

            return __('Tooth :tooth — Surfaces: :surfaces', [
                'tooth' => (string) $toothNumber,
                'surfaces' => implode(', ', $normalized),
            ]);
        }

        return __('General procedure');
    }
}
