<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make([
                    TextEntry::make('name')
                        ->label(__('Name')),
                    TextEntry::make('email')
                        ->label(__('Email')),
                    TextEntry::make('roles.name')
                        ->label(__('Roles'))
                        ->badge(),
                    TextEntry::make('email_verified_at')
                        ->label(__('Email verified at'))
                        ->dateTime()
                        ->placeholder('-'),
                ])->columns()
                    ->columnSpan(2),
            ]);
    }
}
