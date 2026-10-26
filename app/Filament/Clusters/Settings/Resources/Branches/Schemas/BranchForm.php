<?php

namespace App\Filament\Clusters\Settings\Resources\Branches\Schemas;

use App\Models\Branch;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                    ->schema(self::getOpeningHoursComponents()),
            ]);
    }

    /**
     * @return array<int, Grid>
     */
    private static function getOpeningHoursComponents(): array
    {
        $defaults = Branch::defaultOpeningHours();
        $components = [];

        foreach (Branch::daysOfWeek() as $day => $label) {
            $defaultDay = $defaults[$day] ?? ['is_open' => false, 'open_time' => '08:00', 'close_time' => '18:00'];

            $components[] = Grid::make(3)
                ->schema([
                    Toggle::make("opening_hours.{$day}.is_open")
                        ->label($label)
                        ->default((bool) ($defaultDay['is_open'] ?? false))
                        ->live(),
                    TimePicker::make("opening_hours.{$day}.open_time")
                        ->label(__('Opening time'))
                        ->seconds(false)
                        ->default($defaultDay['open_time'] ?? '08:00')
                        ->visible(fn (Get $get): bool => (bool) $get("opening_hours.{$day}.is_open")),
                    TimePicker::make("opening_hours.{$day}.close_time")
                        ->label(__('Closing time'))
                        ->seconds(false)
                        ->default($defaultDay['close_time'] ?? '18:00')
                        ->visible(fn (Get $get): bool => (bool) $get("opening_hours.{$day}.is_open")),
                ]);
        }

        return $components;
    }
}
