<?php

namespace App\Filament\Clusters\Settings\Resources\Branches\Tables;

use App\Models\Branch;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Branch name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->label(__('Branch code'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('—'),
                IconColumn::make('is_main')
                    ->label(__('Main branch'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Branch $record): bool => $record->clinic && $record->clinic->branches()->count() <= 1),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
