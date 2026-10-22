<?php

namespace App\Filament\Resources\Patients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('first_name')
                            ->label(__('First name')),
                        TextEntry::make('last_name')
                            ->label(__('Last name')),
                        TextEntry::make('document_type')
                            ->label(__('Document type'))
                            ->badge(),
                        TextEntry::make('identification_number')
                            ->label(__('Identification number')),
                        TextEntry::make('email')
                            ->label(__('Email address'))
                            ->placeholder('-'),
                        TextEntry::make('phone')
                            ->label(__('Phone')),
                        TextEntry::make('address')
                            ->label(__('Address'))
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
