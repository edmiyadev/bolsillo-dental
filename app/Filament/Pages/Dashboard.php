<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createCommissionRecord')
                ->label(__('New commission record'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(CommissionRecordResource::getUrl('create'))
                ->visible(fn (): bool => auth()->user()?->can('create_commission_records') ?? false),
        ];
    }
}
