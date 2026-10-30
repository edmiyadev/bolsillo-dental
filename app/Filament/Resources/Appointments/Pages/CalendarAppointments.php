<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Widgets\AppointmentCalendarWidget;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class CalendarAppointments extends Page
{
    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament-panels::pages.page';

    public function getTitle(): string
    {
        return __('Appointments Calendar');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label(__('List view'))
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(AppointmentResource::getUrl('index')),
            Action::make('create')
                ->label(__('New appointment'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(AppointmentResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->can('create_appointments') ?? false),
        ];
    }

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AppointmentCalendarWidget::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
