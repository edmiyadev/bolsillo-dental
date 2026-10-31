<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentStatus;
use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class AppointmentFiltersForm
{
    public static function make (): array
    {

        return [
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
        ];
    }
}
