<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\CommissionType;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('default_price')
                    ->label(__('Default price'))
                    ->money()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('default_commission')
                    ->label(__('Default commission'))
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (Service $record): ?string => $record->commission_type === CommissionType::Fixed ? '$' : null)
                    ->suffix(fn (Service $record): ?string => $record->commission_type === CommissionType::Percentage ? '%' : null)
                    ->sortable(),
                TextColumn::make('commission_type')
                    ->label(__('Commission type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('commission_type')
                    ->label(__('Commission type'))
                    ->options(CommissionType::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
