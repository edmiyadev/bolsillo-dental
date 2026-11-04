<?php

namespace App\Filament\Clusters\Settings\Resources\Branches\Schemas;

use App\Enums\DayOfWeek;
use App\Models\Branch;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('General Information'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Branch name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label(__('Branch code'))
                            ->placeholder('MAIN')
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper($state) : null),
                        TextInput::make('phone')
                            ->label(__('Phone'))
                            ->tel()
                            ->maxLength(50),
                        Toggle::make('is_main')
                            ->label(__('Main branch'))
                            ->helperText(__('If checked, this branch is set as the primary branch of the clinic.'))
                            ->default(false),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->disabled(fn (?Branch $record): bool => $record !== null && $record->isOnlyActiveBranch())
                            ->dehydrated()
                            ->rules([
                                fn (?Branch $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                                    if (! $value && $record !== null && $record->isOnlyActiveBranch()) {
                                        $fail(__('You cannot deactivate the only active branch.'));
                                    }
                                },
                            ])
                            ->helperText(fn (?Branch $record): ?string => ($record !== null && $record->isOnlyActiveBranch())
                                ? __('This branch cannot be deactivated because it is the only active branch.')
                                : null
                            )
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Location'))
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('address')
                            ->label(__('Address'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Opening hours'))
                    ->columnSpanFull()
                    ->description(__('Configure weekly operating schedule for this branch.'))
                    ->schema([
                        Repeater::make('opening_hours')
                            ->label(__('Opening hours'))
                            ->table([
                                TableColumn::make(__('Day'))->width('200px'),
                                TableColumn::make(__('Status'))->width('120px'),
                                TableColumn::make(__('Opening time')),
                                TableColumn::make(__('Closing time')),
                            ])
                            ->schema([
                                Hidden::make('day'),
                                TextEntry::make('day_label')
                                    ->label(__('Day'))
                                    ->state(fn (Get $get): string => DayOfWeek::tryFrom((string) $get('day'))?->getLabel() ?? '')
                                    ->hiddenLabel(),
                                Toggle::make('is_open')
                                    ->label(__('Status'))
                                    ->inline(false)
                                    ->hiddenLabel()
                                    ->live(),
                                TimePicker::make('open_time')
                                    ->label(__('Opening time'))
                                    ->seconds(false)
                                    ->hiddenLabel()
                                    ->minutesStep(5)
                                    // ->defaultFocusedDate('08:00')
                                    ->disabled(fn (Get $get): bool => ! (bool) $get('is_open')),
                                TimePicker::make('close_time')
                                    ->label(__('Closing time'))
                                    ->seconds(false)
                                    ->hiddenLabel()
                                    ->minutesStep(5)
                                    ->disabled(fn (Get $get): bool => ! (bool) $get('is_open')),
                            ])
                            ->default(DayOfWeek::defaultSchedule())
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
