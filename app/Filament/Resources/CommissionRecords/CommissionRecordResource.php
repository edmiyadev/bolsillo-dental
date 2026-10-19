<?php

namespace App\Filament\Resources\CommissionRecords;

use App\Filament\Resources\CommissionRecords\Pages\CreateCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\EditCommissionRecord;
use App\Filament\Resources\CommissionRecords\Pages\ListCommissionRecords;
use App\Filament\Resources\CommissionRecords\Schemas\CommissionRecordForm;
use App\Filament\Resources\CommissionRecords\Tables\CommissionRecordsTable;
use App\Models\CommissionRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommissionRecordResource extends Resource
{
    protected static ?string $model = CommissionRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return CommissionRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommissionRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionRecords::route('/'),
            'create' => CreateCommissionRecord::route('/create'),
            'edit' => EditCommissionRecord::route('/{record}/edit'),
        ];
    }
}
