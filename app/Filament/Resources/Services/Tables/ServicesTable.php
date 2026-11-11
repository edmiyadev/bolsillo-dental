<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enums\CommissionType;
use App\Models\Service;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
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
                TextColumn::make('creator.name')
                    ->label(__('Created by'))
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->can('view_all_services') ?? false),
                TextColumn::make('default_price')
                    ->label(__('Default price'))
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('default_commission')
                    ->label(__('Default commission'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: '.')
                    ->prefix(fn (Service $record): ?string => $record->commission_type === CommissionType::Fixed ? 'RD$' : null)
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
                TextColumn::make('deleted_at')
                    ->label(__('Deleted at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('created_by')
                    ->label(__('Created by'))
                    ->relationship('creator', 'name')
                    ->visible(fn (): bool => auth()->user()?->can('view_all_services') ?? false),
                SelectFilter::make('commission_type')
                    ->label(__('Commission type'))
                    ->options(CommissionType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
