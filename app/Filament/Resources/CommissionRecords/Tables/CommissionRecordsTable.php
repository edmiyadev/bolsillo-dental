<?php

namespace App\Filament\Resources\CommissionRecords\Tables;

use App\Actions\Commissions\MarkCommissionRecordsAsPaidAction;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\CommissionRecord;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommissionRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('worked_at')
                    ->label(__('Worked at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->label(__('Service'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('Price'))
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('commission')
                    ->label(__('Commission'))
                    ->numeric(decimalPlaces: 2, decimalSeparator: '.')
                    ->prefix(fn (CommissionRecord $record): ?string => $record->commission_type === CommissionType::Fixed ? 'RD$' : null)
                    ->suffix(fn (CommissionRecord $record): ?string => $record->commission_type === CommissionType::Percentage ? '%' : null)
                    ->sortable(),
                TextColumn::make('commission_type')
                    ->label(__('Commission type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('calculated_earnings')
                    ->label(__('Calculated earnings'))
                    ->money('DOP', locale: 'es_DO')
                    ->sortable()
                    ->summarize(Sum::make()->money('DOP', locale: 'es_DO')),
                TextColumn::make('status')
                    ->label(__('Status'))
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
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(CommissionStatus::class),
                SelectFilter::make('service')
                    ->label(__('Service'))
                    ->relationship('service', 'name'),
                Filter::make('worked_at')
                    ->label(__('Worked at'))
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('worked_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('worked_at', '<=', $date),
                            );
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('markAsPaid')
                    ->label(__('Mark as paid'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (CommissionRecord $record): bool => $record->status !== CommissionStatus::Paid && ! $record->trashed())
                    ->requiresConfirmation()
                    ->action(function (CommissionRecord $record, MarkCommissionRecordsAsPaidAction $markAsPaid): void {
                        $markAsPaid->handle($record);

                        Notification::make()
                            ->title(__('Commission record marked as paid'))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsPaid')
                        ->label(__('Mark as paid'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, MarkCommissionRecordsAsPaidAction $markAsPaid): void {
                            $count = $markAsPaid->handle($records);

                            Notification::make()
                                ->title(__(':count commission records marked as paid', ['count' => $count]))
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
