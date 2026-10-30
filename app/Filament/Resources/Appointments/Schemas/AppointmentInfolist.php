<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Models\Appointment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label(__('Branch')),
                        TextEntry::make('patient_full_name')
                            ->label(__('Patient'))
                            ->state(fn (Appointment $record): string => $record->patient
                                ? "{$record->patient->first_name} {$record->patient->last_name}"
                                : '-'),
                        TextEntry::make('dentist.name')
                            ->label(__('Dentist')),
                        TextEntry::make('cubicle.name')
                            ->label(__('Cubicle')),
                        TextEntry::make('reason')
                            ->label(__('Consultation reason'))
                            ->state(fn (Appointment $record): string => $record->reasonLabel() ?: '-'),
                        TextEntry::make('specialty')
                            ->label(__('Specialty'))
                            ->badge(),
                        TextEntry::make('starts_at')
                            ->label(__('Date and time'))
                            ->dateTime(),
                        TextEntry::make('duration_minutes')
                            ->label(__('Duration (minutes)'))
                            ->suffix(' '.__('min')),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('observations')
                            ->label(__('Observations'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('Updated at'))
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
