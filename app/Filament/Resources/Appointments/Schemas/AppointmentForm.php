<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Models\Branch;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Rules\AppointmentAvailabilityRule;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('is_custom_reason')
                            ->default(false)
                            ->dehydrated()
                            ->dehydrateStateUsing(fn (Get $get, mixed $state): bool => $get('service_id') === 'other' || (bool) $state),
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->relationship('branch', 'name', fn(Builder $query) => $query->active())
                            ->default(fn (): mixed => request()->query('branch_id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('dentist_id', null);
                                $set('cubicle_id', null);
                                $set('service_id', null);
                                $set('is_custom_reason', false);
                                $set('custom_reason', null);
                            })
                            ->required(),
                        Select::make('patient_id')
                            ->label(__('Patient'))
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => "{$record->first_name} {$record->last_name} ({$record->identification_number})")
                            ->createOptionForm(fn(Schema $schema) => PatientForm::configure($schema))
                            ->searchable(['first_name', 'last_name', 'identification_number'])
                            ->columnStart(1)
                            ->preload()
                            ->required(),
                        Select::make('service_id')
                            ->label(__('Service'))
                            ->options(fn (): array => Service::pluck('name', 'id')->toArray() + ['other' => __('Other')])
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?Model $record, Set $set, Get $get): void {
                                if ($record?->is_custom_reason || $get('is_custom_reason')) {
                                    $component->state('other');
                                    $set('is_custom_reason', true);
                                } elseif ($record?->service_id) {
                                    $service = Service::withoutGlobalScopes()?->find($record->service_id);
                                    if ($service) {
                                        $specialty = $service->specialty ?? DentalSpecialty::General;
                                        $set('specialty', $specialty instanceof DentalSpecialty ? $specialty->value : (string) $specialty);
                                    }
                                }
                            })
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                $isOther = ($state === 'other');

                                $set('is_custom_reason', $isOther);

                                if ($isOther) {
                                    $set('duration_minutes', 30);
                                    $set('specialty', DentalSpecialty::General->value);

                                    $dentistId = $get('dentist_id');
                                    if (filled($dentistId)) {
                                        $dentist = User::find($dentistId);
                                        if ($dentist && ! $dentist->supportsSpecialty(DentalSpecialty::General)) {
                                            $set('dentist_id', null);
                                        }
                                    }

                                    return;
                                }

                                $set('custom_reason', null);

                                if (blank($state)) {
                                    $set('duration_minutes', 30);
                                    $set('specialty', null);

                                    return;
                                }

                                $service = Service::withoutGlobalScopes()?->find($state);

                                if (! $service) {
                                    return;
                                }

                                $set('duration_minutes', $service->duration_minutes ?? 30);
                                $specialty = $service->specialty ?? DentalSpecialty::General;
                                $set('specialty', $specialty instanceof DentalSpecialty ? $specialty->value : (string) $specialty);

                                $dentistId = $get('dentist_id');
                                if (filled($dentistId)) {
                                    $dentist = User::find($dentistId);
                                    if ($dentist && ! $dentist->supportsSpecialty($specialty)) {
                                        $set('dentist_id', null);
                                    }
                                }

                                $cubicleId = $get('cubicle_id');
                                if (filled($cubicleId)) {
                                    $cubicle = Cubicle::find($cubicleId);
                                    if ($cubicle && ! $cubicle->supportsSpecialty($specialty)) {
                                        $set('cubicle_id', null);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(fn (mixed $state): ?int => ($state === 'other' || blank($state)) ? null : (int) $state)
                            ->required(fn (Get $get): bool => ! (bool) $get('is_custom_reason')),
                        TextInput::make('custom_reason')
                            ->label(__('Consultation reason'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('service_id') === 'other' || (bool) $get('is_custom_reason'))
                            ->required(fn (Get $get): bool => $get('service_id') === 'other' || (bool) $get('is_custom_reason')),
                        Select::make('dentist_id')
                            ->label(__('Dentist'))
                            ->options(function (Get $get): array {
                                $branchId = $get('branch_id');
                                $selectedDentistId = $get('dentist_id');
                                $serviceId = $get('service_id');
                                $specialty = $get('specialty');

                                $requiredSpecialty = null;
                                if (filled($serviceId) && $serviceId !== 'other') {
                                    $service = Service::withoutGlobalScopes()?->find($serviceId);
                                    $requiredSpecialty = $service?->specialty;
                                } elseif (filled($specialty)) {
                                    $requiredSpecialty = $specialty;
                                }

                                return User::query()
                                    ->role(RoleEnum::Dentist->value)
                                    ->when(
                                        filled($branchId),
                                        fn (Builder $query): Builder => $query->where(function (Builder $subQuery) use ($branchId, $selectedDentistId): void {
                                            $subQuery->whereHas(
                                                'branchSchedules',
                                                fn (Builder $scheduleQuery): Builder => $scheduleQuery
                                                    ->where('branch_id', $branchId)
                                                    ->where('is_active', true),
                                            );

                                            if (filled($selectedDentistId)) {
                                                $subQuery->orWhere('id', $selectedDentistId);
                                            }
                                        }),
                                    )
                                    ->get()
                                    ->filter(function (User $dentist) use ($selectedDentistId, $requiredSpecialty): bool {
                                        if (filled($selectedDentistId) && (string) $dentist->id === (string) $selectedDentistId) {
                                            return true;
                                        }

                                        if (blank($requiredSpecialty)) {
                                            return true;
                                        }

                                        return $dentist->supportsSpecialty($requiredSpecialty);
                                    })
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    $serviceId = $get('service_id');
                                    $specialty = $get('specialty');

                                    $requiredSpecialty = null;
                                    if (filled($serviceId) && $serviceId !== 'other') {
                                        $service = Service::withoutGlobalScopes()?->find($serviceId);
                                        $requiredSpecialty = $service?->specialty;
                                    } elseif (filled($specialty)) {
                                        $requiredSpecialty = $specialty;
                                    }

                                    if (! $requiredSpecialty) {
                                        return;
                                    }

                                    $dentist = User::find($value);
                                    if (! $dentist || ! $dentist->supportsSpecialty($requiredSpecialty)) {
                                        $fail(__('The selected dentist does not support the required specialty.'));
                                    }
                                },
                            ])
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label(__('Duration (minutes)'))
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(480)
                            ->default(30)
                            ->suffix(__('min'))
                            ->required(),
                        Select::make('cubicle_id')
                            ->label(__('Cubicle'))
                            ->options(function (Get $get): array {
                                $branchId = $get('branch_id');
                                $selectedCubicleId = $get('cubicle_id');
                                $serviceId = $get('service_id');
                                $specialty = $get('specialty');

                                $requiredSpecialty = null;
                                if (filled($serviceId) && $serviceId !== 'other') {
                                    $service = Service::withoutGlobalScopes()?->find($serviceId);
                                    $requiredSpecialty = $service?->specialty;
                                } elseif (filled($specialty)) {
                                    $requiredSpecialty = $specialty;
                                }

                                return Cubicle::query()
                                    ->when(
                                        filled($branchId),
                                        fn (Builder $query): Builder => $query->where('branch_id', $branchId),
                                    )
                                    ->where(function (Builder $query) use ($selectedCubicleId): void {
                                        $query->where('status', CubicleStatus::Active);

                                        if (filled($selectedCubicleId)) {
                                            $query->orWhere('id', $selectedCubicleId);
                                        }
                                    })
                                    ->get()
                                    ->filter(function (Cubicle $cubicle) use ($selectedCubicleId, $requiredSpecialty): bool {
                                        if (filled($selectedCubicleId) && (string) $cubicle->id === (string) $selectedCubicleId) {
                                            return true;
                                        }

                                        if (blank($requiredSpecialty)) {
                                            return true;
                                        }

                                        return $cubicle->supportsSpecialty($requiredSpecialty instanceof DentalSpecialty ? $requiredSpecialty : (string) $requiredSpecialty);
                                    })
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label(__('Date and time'))
                            ->seconds(false)
                            ->minDate(fn ()  => now())
                            ->default(fn (): mixed => request()->query('starts_at'))
                            ->required()
                            ->rule(fn (?Model $record): AppointmentAvailabilityRule => new AppointmentAvailabilityRule($record?->getKey())),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(AppointmentStatus::class)
                            ->default(AppointmentStatus::Scheduled)
                            ->required(),
                        Textarea::make('observations')
                            ->label(__('Observations'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
