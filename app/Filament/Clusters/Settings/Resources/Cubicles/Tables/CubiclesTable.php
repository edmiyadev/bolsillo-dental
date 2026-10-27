<?php

namespace App\Filament\Clusters\Settings\Resources\Cubicles\Tables;

use App\Enums\CubicleStatus;
use App\Enums\DentalSpecialty;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CubiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Cubicle code'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Cubicle name'))
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->searchable()
                    ->sortable()
                    ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager),
                TextColumn::make('specialty_types')
                    ->label(__('Specialties'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DentalSpecialty::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state): string => DentalSpecialty::tryFrom($state)?->getColor() ?? 'gray'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->hidden(fn ($livewire): bool => $livewire instanceof RelationManager),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(CubicleStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
