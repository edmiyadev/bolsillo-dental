<?php

namespace App\Filament\Clusters\Settings\Resources\Cubicles\Schemas;

use App\Enums\CubicleStatus;
use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CubicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('General Information'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('branch_id')
                            ->label(__('Branch'))
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn ($livewire): bool => ! ($livewire instanceof RelationManager)),
                        TextInput::make('name')
                            ->label(__('Cubicle name'))
                            ->placeholder('Cubículo 1 - General')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label(__('Cubicle code'))
                            ->placeholder(__('Auto-generated (e.g. CUB-01)'))
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(CubicleStatus::class)
                            ->default(CubicleStatus::Active)
                            ->required(),
                        Select::make('specialty_types')
                            ->label(__('Supported specialties'))
                            ->multiple()
                            ->options(DentalSpecialty::class)
                            ->default([DentalSpecialty::General->value])
                            ->required()
                            ->helperText(__('Specialties that this cubicle is equipped and available for.'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Reserved Specialty Schedules'))
                    ->columnSpanFull()
                    ->description(__('Configure specific days/hours when this cubicle is reserved exclusively for a specialty (e.g. Orthodontics on Thursdays). During other times, it remains available for general use.'))
                    ->schema([
                        Repeater::make('specialty_schedules')
                            ->label(__('Schedule rules'))
                            ->addActionLabel(__('Add schedule rule'))
                            ->default([])
                            ->schema([
                                Select::make('day')
                                    ->label(__('Day'))
                                    ->options(DayOfWeek::class)
                                    ->required(),
                                Select::make('specialty')
                                    ->label(__('Specialty'))
                                    ->options(DentalSpecialty::class)
                                    ->required(),
                                TimePicker::make('start_time')
                                    ->label(__('Start time'))
                                    ->seconds(false)
                                    ->minutesStep(5)
                                    ->required(),
                                TimePicker::make('end_time')
                                    ->label(__('End time'))
                                    ->seconds(false)
                                    ->minutesStep(5)
                                    ->required(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
