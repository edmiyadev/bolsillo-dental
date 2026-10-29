<?php

namespace App\Filament\Clusters\Settings\Resources\Branches\RelationManagers;

use App\Filament\Clusters\Settings\Resources\Cubicles\Schemas\CubicleForm;
use App\Filament\Clusters\Settings\Resources\Cubicles\Tables\CubiclesTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CubiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'cubicles';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAny', \App\Models\Cubicle::class) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Cubicles');
    }

    public static function getModelLabel(): ?string
    {
        return __('Cubicle');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('Cubicles');
    }

    public function form(Schema $schema): Schema
    {
        return CubicleForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return CubiclesTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
