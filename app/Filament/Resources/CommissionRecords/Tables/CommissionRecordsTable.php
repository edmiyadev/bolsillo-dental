<?php

namespace App\Filament\Resources\CommissionRecords\Tables;

use App\Actions\Commissions\MarkCommissionRecordsAsPaidAction;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Models\CommissionRecord;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
                    ->date()
                    ->sortable(),
                TextColumn::make('service.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money()
                    ->sortable()
                    ->placeholder('-')
                    ->summarize(Sum::make()->money()),
                TextColumn::make('commission')
                    ->numeric(decimalPlaces: 2)
                    ->prefix(fn (CommissionRecord $record): ?string => $record->commission_type === CommissionType::Fixed ? '$' : null)
                    ->suffix(fn (CommissionRecord $record): ?string => $record->commission_type === CommissionType::Percentage ? '%' : null)
                    ->sortable(),
                TextColumn::make('commission_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('calculated_earnings')
                    ->money()
                    ->sortable()
                    ->summarize(Sum::make()->money()),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(CommissionStatus::class),
                SelectFilter::make('service')
                    ->relationship('service', 'name'),
                Filter::make('worked_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
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
            ])
            ->recordActions([
                Action::make('markAsPaid')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (CommissionRecord $record): bool => $record->status !== CommissionStatus::Paid)
                    ->requiresConfirmation()
                    ->action(function (CommissionRecord $record, MarkCommissionRecordsAsPaidAction $markAsPaid): void {
                        $markAsPaid->handle($record);

                        Notification::make()
                            ->title('Commission record marked as paid')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markAsPaid')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records, MarkCommissionRecordsAsPaidAction $markAsPaid): void {
                            $count = $markAsPaid->handle($records);

                            Notification::make()
                                ->title("{$count} commission records marked as paid")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
