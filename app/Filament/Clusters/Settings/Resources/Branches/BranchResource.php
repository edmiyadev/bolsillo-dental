<?php

namespace App\Filament\Clusters\Settings\Resources\Branches;

use App\Filament\Clusters\Settings\Resources\Branches\Pages\CreateBranch;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\EditBranch;
use App\Filament\Clusters\Settings\Resources\Branches\Pages\ListBranches;
use App\Filament\Clusters\Settings\Resources\Branches\Schemas\BranchForm;
use App\Filament\Clusters\Settings\Resources\Branches\Tables\BranchesTable;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Branch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Branch');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Branches');
    }

    public static function getNavigationLabel(): string
    {
        return __('Branches');
    }

    public static function form(Schema $schema): Schema
    {
        return BranchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BranchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBranches::route('/'),
            'create' => CreateBranch::route('/create'),
            'edit' => EditBranch::route('/{record}/edit'),
        ];
    }
}
