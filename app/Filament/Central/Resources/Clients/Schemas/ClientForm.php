<?php

namespace App\Filament\Central\Resources\Clients\Schemas;

use App\Models\Client;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Client Information'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(self::getFormComponents()),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function getFormComponents(): array
    {
        return [
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('Contact email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(table: Client::class, column: 'email', ignoreRecord: true),
            TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(50),
            TextInput::make('document_number')
                ->label(__('Document (ID / RNC)'))
                ->maxLength(50),
            Textarea::make('address')
                ->label(__('Address'))
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true)
                ->required()
                ->columnSpanFull(),
        ];
    }
}
