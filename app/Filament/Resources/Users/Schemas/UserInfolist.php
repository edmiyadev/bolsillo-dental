<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\BranchUser;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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

                Section::make(__('Branches and Schedules'))
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('branchSchedules')
                            ->hiddenLabel()
                            ->placeholder(__('No branches assigned'))
                            ->columns(2)
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label(__('Branch')),
                                IconEntry::make('is_active')
                                    ->label(__('Active'))
                                    ->boolean(),
                                RepeatableEntry::make('working_schedule')
                                    ->label(__('Working days'))
                                    ->state(fn (BranchUser $record): array => $record->workingScheduleRows())
                                    ->placeholder('-')
                                    ->columnSpanFull()
                                    ->table([
                                        TableColumn::make(__('Day')),
                                        TableColumn::make(__('Start time')),
                                        TableColumn::make(__('End time')),
                                    ])
                                    ->schema([
                                        TextEntry::make('day_label')
                                            ->hiddenLabel(),
                                        TextEntry::make('open_time')
                                            ->hiddenLabel(),
                                        TextEntry::make('close_time')
                                            ->hiddenLabel(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
