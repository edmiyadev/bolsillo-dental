<?php

namespace App\Filament\Resources\CommissionRecords\Pages;

use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionRecords extends ListRecords
{
    protected static string $resource = CommissionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
