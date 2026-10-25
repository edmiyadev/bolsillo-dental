<?php

namespace App\Filament\Central\Resources\Tenants\Tables;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Client name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('id')
                    ->label(__('Identifier (Slug)'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('database_name')
                    ->label(__('Database name'))
                    ->state(fn (Tenant $record): string => $record->databaseName())
                    ->badge()
                    ->color('info'),
                TextColumn::make('domains.domain')
                    ->label(__('Domains'))
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Contact email'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                Action::make('open_domain')
                    ->label(__('Open panel'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(function (Tenant $record): ?string {
                        $domain = $record->domains->first()?->domain;

                        if (blank($domain)) {
                            return null;
                        }

                        $scheme = request()->getScheme() ?: 'http';
                        $port = request()->getPort();
                        $portSuffix = ($port && ! in_array($port, [80, 443], true)) ? ':'.$port : '';

                        return "{$scheme}://{$domain}{$portSuffix}";
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (Tenant $record): bool => $record->domains->isNotEmpty()),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
