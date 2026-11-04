<?php

namespace App\Filament\Clusters\Settings\Resources\Cubicles;

use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\CreateCubicle;
use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\EditCubicle;
use App\Filament\Clusters\Settings\Resources\Cubicles\Pages\ListCubicles;
use App\Filament\Clusters\Settings\Resources\Cubicles\Schemas\CubicleForm;
use App\Filament\Clusters\Settings\Resources\Cubicles\Tables\CubiclesTable;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Cubicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CubicleResource extends Resource
{
    protected static ?string $model = Cubicle::class;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Cubicle');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Cubicles');
    }

    public static function getNavigationLabel(): string
    {
        return __('Cubicles');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return CubicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CubiclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCubicles::route('/'),
            'create' => CreateCubicle::route('/create'),
            'edit' => EditCubicle::route('/{record}/edit'),
        ];
    }
}
