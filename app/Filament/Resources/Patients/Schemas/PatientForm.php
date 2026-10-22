<?php

namespace App\Filament\Resources\Patients\Schemas;

use App\Enums\IdentificationType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('First name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label(__('Last name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('document_type')
                            ->label(__('Document type'))
                            ->options(IdentificationType::class)
                            ->required(),
                        TextInput::make('identification_number')
                            ->label(__('Identification number'))
                            ->unique()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email address'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->required()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label(__('Address'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
