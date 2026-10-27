<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make([
                    TextEntry::make('name')
                        ->label(__('Name')),
                    TextEntry::make('permissions.name')
                        ->label(__('Permissions'))
                        ->formatStateUsing(fn ($state) => Str::of($state)->explode('_')->map(fn ($s) => __(ucfirst($s)))->implode(' '))
                        ->badge()
                        ->columnSpanFull(),
                ])->columns()
                    ->columnSpan(2),
            ]);
    }
}
