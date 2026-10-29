<?php

namespace App\Filament\Resources\CommissionRecords\Pages;

use App\Filament\Resources\CommissionRecords\CommissionRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionRecord extends EditRecord
{
    protected static string $resource = CommissionRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
