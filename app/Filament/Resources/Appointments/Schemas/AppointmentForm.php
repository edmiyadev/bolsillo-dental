<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Filament\Resources\Patients\Schemas\PatientForm;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Rules\AppointmentAvailabilityRule;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->relationship('branch', 'name', fn (Builder $query) => $query->active())
                            ->default(fn (): mixed => request()->query('branch_id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('dentist_id', null);
                                $set('custom_reason', null);
                            })
                            ->required(),
                        Select::make('patient_id')
                            ->label(__('Patient'))
                            ->relationship('patient', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => "{$record->first_name} {$record->last_name} ({$record->identification_number})")
                            ->createOptionForm(fn (Schema $schema) => PatientForm::configure($schema))
                            ->searchable(['first_name', 'last_name', 'identification_number'])
                            ->preload()
                            ->required(),
                        Select::make('service_id')
                            ->label(__('Service'))
                            ->options(fn (): array => Service::pluck('name', 'id')->toArray() + ['other' => __('Other')])
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateHydrated(fn (Select $component, ?Model $record) => $record?->is_custom_reason && $component->state('other'))
                            ->afterStateUpdated(fn (Set $set, Get $get, mixed $state) => static::handleServiceUpdated($set, $get, $state))
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label(__('Duration (minutes)'))
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(480)
                            ->default(30)
                            ->suffix(__('min'))
                            ->required(),
                        TextInput::make('custom_reason')
                            ->label(__('Consultation reason'))
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('service_id') === 'other')
                            ->required(fn (Get $get): bool => $get('service_id') === 'other'),
                        Select::make('dentist_id')
                            ->label(__('Dentist'))
                            ->options(fn (Get $get): array => static::getDentistOptions($get))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->rules([fn (Get $get): Closure => static::validateDentistSpecialty($get)])
                            ->columnSpan(fn (string $operation): int => $operation === 'create' ? 2 : 1)
                            ->required(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(AppointmentStatus::class)
                            ->default(AppointmentStatus::Scheduled)
                            ->hiddenOn('create')
                            ->required(fn (string $operation): bool => $operation === 'edit'),
                        DateTimePicker::make('starts_at')
                            ->label(__('Date and time'))
                            ->seconds(false)
                            ->minDate(fn () => now())
                            ->default(fn (): mixed => request()->query('starts_at'))
                            ->columnSpanFull()
                            ->required()
                            ->rule(fn (?Model $record): AppointmentAvailabilityRule => new AppointmentAvailabilityRule($record?->getKey())),
                        Textarea::make('observations')
                            ->label(__('Observations'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function handleServiceUpdated(Set $set, Get $get, mixed $state): void
    {
        $set('custom_reason', null);

        if (blank($state)) {
            $set('duration_minutes', 30);

            return;
        }

        $specialty = static::resolveRequiredSpecialty($state);
        $service = $state === 'other' ? null : Service::withoutGlobalScopes()?->find($state);

        $set('duration_minutes', $service?->duration_minutes ?? 30);

        $dentistId = $get('dentist_id');
        if (filled($dentistId) && $specialty) {
            $dentist = User::find($dentistId);
            if ($dentist && ! $dentist->supportsSpecialty($specialty)) {
                $set('dentist_id', null);
            }
        }
    }

    public static function getDentistOptions(Get $get): array
    {
        $branchId = $get('branch_id');
        $selectedId = $get('dentist_id');
        $specialty = static::resolveRequiredSpecialty($get('service_id'));

        return User::query()
            ->role(RoleEnum::Dentist->value)
            ->when(filled($branchId), fn (Builder $q) => $q->where(function (Builder $sub) use ($branchId, $selectedId): void {
                $sub->whereHas('branchSchedules', fn (Builder $s) => $s->where('branch_id', $branchId)->where('is_active', true));

                if (filled($selectedId)) {
                    $sub->orWhere('id', $selectedId);
                }
            }))
            ->get()
            ->filter(fn (User $dentist): bool => (filled($selectedId) && (string) $dentist->id === (string) $selectedId)
                || blank($specialty)
                || $dentist->supportsSpecialty($specialty)
            )
            ->pluck('name', 'id')
            ->all();
    }

    public static function validateDentistSpecialty(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            if (blank($value)) {
                return;
            }

            $specialty = static::resolveRequiredSpecialty($get('service_id'));
            if ($specialty && ! User::find($value)?->supportsSpecialty($specialty)) {
                $fail(__('The selected dentist does not support the required specialty.'));
            }
        };
    }

    public static function resolveRequiredSpecialty(mixed $serviceId): ?DentalSpecialty
    {
        if (blank($serviceId)) {
            return null;
        }

        if ($serviceId === 'other') {
            return DentalSpecialty::General;
        }

        return Service::withoutGlobalScopes()?->find($serviceId)?->specialty ?? DentalSpecialty::General;
    }
}
