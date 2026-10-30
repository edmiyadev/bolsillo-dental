<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label(__('Calendar view'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(AppointmentResource::getUrl('calendar')),
            CreateAction::make(),
        ];
    }
}
