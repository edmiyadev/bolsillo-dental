<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\DayOfWeek;
use App\Enums\DentalSpecialty;
use App\Enums\RoleEnum;
use App\Models\Branch;
use App\Rules\CollaboratorBranchSchedulesRule;
use App\ValueObjects\WeeklySchedule;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255),
                        Section::make()
                            ->columnSpanFull()
                            ->schema([
                                Select::make('roles')
                                    ->label(__('Roles'))
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereNot('name', RoleEnum::SuperAdmin->value),
                                    )
                                    ->preload()
                                    ->multiple(),
                                Select::make('specialties')
                                    ->label(__('Specialties'))
                                    ->options(DentalSpecialty::class)
                                    ->multiple()
                                    ->default([DentalSpecialty::General->value])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make(__('Branches and Schedules'))
                    ->description(__('Assign the collaborator to one or more branches and configure their working hours in each.'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('branchSchedules')
                            ->label(__('Assigned branches'))
                            ->relationship('branchSchedules')
                            ->addActionLabel(__('Assign branch'))
                            ->defaultItems(0)
                            ->columns(2)
                            ->columnSpanFull()
                            ->rules([new CollaboratorBranchSchedulesRule])
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('Branch'))
                                    ->options(function (Get $get): array {
                                        $selectedId = $get('branch_id');

                                        return Branch::query()
                                            ->where(function (Builder $query) use ($selectedId): void {
                                                $query->where('is_active', true);

                                                if (filled($selectedId)) {
                                                    $query->orWhere('id', $selectedId);
                                                }
                                            })
                                            ->pluck('name', 'id')
                                            ->all();
                                    })
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $branch = Branch::query()->find($state);
                                        $openingHours = $branch?->opening_hours instanceof WeeklySchedule
                                            ? $branch->opening_hours->toArray()
                                            : (is_array($branch?->opening_hours) && $branch->opening_hours !== []
                                                ? $branch->opening_hours
                                                : DayOfWeek::defaultSchedule());

                                        $set('schedule', $openingHours);
                                    }),
                                Toggle::make('is_active')
                                    ->label(__('Active'))
                                    ->inline(false)
                                    ->default(true),
                                Repeater::make('schedule')
                                    ->label(__('Weekly schedule'))
                                    ->table([
                                        TableColumn::make(__('Day'))->width('200px'),
                                        TableColumn::make(__('Works'))->width('120px'),
                                        TableColumn::make(__('Start time')),
                                        TableColumn::make(__('End time')),
                                    ])
                                    ->schema([
                                        Hidden::make('day'),
                                        TextEntry::make('day_label')
                                            ->label(__('Day'))
                                            ->state(fn (Get $get): string => DayOfWeek::tryFrom((string) $get('day'))?->getLabel() ?? '')
                                            ->hiddenLabel(),
                                        Toggle::make('is_open')
                                            ->label(__('Works'))
                                            ->inline(false)
                                            ->hiddenLabel()
                                            ->live(),
                                        TimePicker::make('open_time')
                                            ->label(__('Start time'))
                                            ->seconds(false)
                                            ->hiddenLabel()
                                            ->minutesStep(5)
                                            ->disabled(fn (Get $get): bool => ! (bool) $get('is_open')),
                                        TimePicker::make('close_time')
                                            ->label(__('End time'))
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
                    ]),
            ]);
    }
}
