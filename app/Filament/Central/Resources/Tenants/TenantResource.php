<?php

namespace App\Filament\Central\Resources\Tenants;

use App\Filament\Central\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Central\Resources\Tenants\Pages\EditTenant;
use App\Filament\Central\Resources\Tenants\Pages\ListTenants;
use App\Filament\Central\Resources\Tenants\Schemas\TenantForm;
use App\Filament\Central\Resources\Tenants\Tables\TenantsTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Clinic / Instance');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Clinics / Instances');
    }

    public static function getNavigationLabel(): string
    {
        return __('Clinics / Instances');
    }

    public static function form(Schema $schema): Schema
    {
        return TenantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
