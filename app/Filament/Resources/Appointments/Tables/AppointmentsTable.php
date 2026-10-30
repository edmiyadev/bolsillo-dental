<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\DentalSpecialty;
use App\Models\Appointment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('starts_at')
                    ->label(__('Date and time'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('patient_name')
                    ->label(__('Patient'))
                    ->state(fn (Appointment $record): string => $record->patient
                        ? "{$record->patient->first_name} {$record->patient->last_name}"
                        : '-')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('patient', function (Builder $patientQuery) use ($search): void {
                            $patientQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('identification_number', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('dentist.name')
                    ->label(__('Dentist'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->sortable(),
                TextColumn::make('cubicle.name')
                    ->label(__('Cubicle'))
                    ->sortable(),
                TextColumn::make('reason')
                    ->label(__('Consultation reason'))
                    ->state(fn (Appointment $record): string => $record->reasonLabel()),
                TextColumn::make('specialty')
                    ->label(__('Specialty'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label(__('Duration (minutes)'))
                    ->suffix(' '.__('min'))
                    ->sortable(),
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
                SelectFilter::make('branch')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name'),
                SelectFilter::make('dentist')
                    ->label(__('Dentist'))
                    ->relationship('dentist', 'name'),
                SelectFilter::make('specialty')
                    ->label(__('Specialty'))
                    ->options(DentalSpecialty::class),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(AppointmentStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
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
