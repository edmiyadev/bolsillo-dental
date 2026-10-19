<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\CommissionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('default_price')
                    ->label(__('Default price'))
                    ->required(fn (Get $get): bool => in_array($get('commission_type'), [CommissionType::Percentage, CommissionType::Percentage->value], true))
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
                Select::make('commission_type')
                    ->label(__('Commission type'))
                    ->options(CommissionType::class)
                    ->default(CommissionType::Percentage)
                    ->live()
                    ->required(),
                TextInput::make('default_commission')
                    ->label(__('Default commission'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (Get $get): ?int => $get('commission_type') === CommissionType::Percentage || $get('commission_type') === CommissionType::Percentage->value ? 100 : null)
                    ->prefix(fn (Get $get): ?string => $get('commission_type') === CommissionType::Fixed || $get('commission_type') === CommissionType::Fixed->value ? '$' : null)
                    ->suffix(fn (Get $get): ?string => $get('commission_type') === CommissionType::Percentage || $get('commission_type') === CommissionType::Percentage->value ? '%' : null),
            ]);
    }
}
