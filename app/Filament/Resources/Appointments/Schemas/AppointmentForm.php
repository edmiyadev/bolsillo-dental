<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\CubicleStatus;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Models\Cubicle;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Rules\AppointmentAvailabilityRule;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->options(function (Get $get): array {
                                $selectedId = $get('branch_id');

                                return Branch::query()
                                    ->where(function (Builder $query) use ($selectedId): void {
                                        $query->where('is_active', true);

                                        if (filled($selectedId)) {
                                            $query->orWhere('id', $selectedId);
                                        }
                                    })
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->default(fn (): mixed => request()->query('branch_id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('patient_id')
                            ->label(__('Patient'))
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => "{$record->first_name} {$record->last_name} ({$record->identification_number})")
                            ->searchable(['first_name', 'last_name', 'identification_number'])
                            ->preload()
                            ->required(),
                        Select::make('dentist_id')
                            ->label(__('Dentist'))
                            ->options(function (Get $get): array {
                                $branchId = $get('branch_id');
                                $selectedDentistId = $get('dentist_id');

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
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Toggle::make('is_custom_reason')
                            ->label(__('Other reason (not a predefined service)'))
                            ->default(false)
                            ->inline(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, bool $state): void {
                                if ($state) {
                                    $set('service_id', null);
                                }
                            }),
                        Select::make('service_id')
                            ->label(__('Service'))
                            ->options(function (Get $get): array {
                                $dentistId = $get('dentist_id');

                                return Service::withoutGlobalScopes()
                                    ->whereNull('deleted_at')
                                    ->when(
                                        filled($dentistId),
                                        fn (Builder $query): Builder => $query->where('user_id', $dentistId),
                                    )
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => ! (bool) $get('is_custom_reason'))
                            ->required(fn (Get $get): bool => ! (bool) $get('is_custom_reason'))
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if (blank($state)) {
                                    return;
                                }

                                $service = Service::withoutGlobalScopes()->find($state);
                                if (! $service) {
                                    return;
                                }

                                $set('duration_minutes', $service->duration_minutes ?? 30);
                                $set('specialty', $service->specialty?->value ?? DentalSpecialty::General->value);
                            }),
                        TextInput::make('custom_reason')
                            ->label(__('Consultation reason'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('is_custom_reason'))
                            ->required(fn (Get $get): bool => (bool) $get('is_custom_reason')),
                        Select::make('specialty')
                            ->label(__('Specialty'))
                            ->options(DentalSpecialty::class)
                            ->default(DentalSpecialty::General)
                            ->live()
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
                                $specialty = $get('specialty');

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
                                    ->filter(function (Cubicle $cubicle) use ($selectedCubicleId, $specialty): bool {
                                        if (filled($selectedCubicleId) && (string) $cubicle->id === (string) $selectedCubicleId) {
                                            return true;
                                        }

                                        if (blank($specialty)) {
                                            return true;
                                        }

                                        return $cubicle->supportsSpecialty($specialty instanceof DentalSpecialty ? $specialty : (string) $specialty);
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
