<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\DayOfWeek;
use App\Enums\RoleEnum;
use App\Models\Branch;
use Closure;
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
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! is_array($value) || $value === []) {
                                        return;
                                    }

                                    self::validateBranchSchedules($value, $fail);
                                },
                            ])
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('Branch'))
                                    ->options(fn (): array => Branch::query()->where('is_active', true)->pluck('name', 'id')->all())
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $branch = Branch::query()->find($state);
                                        $openingHours = is_array($branch?->opening_hours) && $branch->opening_hours !== []
                                            ? $branch->opening_hours
                                            : DayOfWeek::defaultSchedule();

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

    /**
     * @param  array<int|string, mixed>  $items
     */
    protected static function validateBranchSchedules(array $items, Closure $fail): void
    {
        $branchIds = collect($items)
            ->pluck('branch_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($branchIds) !== count(array_unique($branchIds))) {
            $fail(__('A collaborator cannot have the same branch assigned more than once.'));

            return;
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->get()
            ->keyBy('id');

        /** @var array<string, array<int, array{branch_name: string, open_time: string, close_time: string}>> $activeDayIntervals */
        $activeDayIntervals = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['branch_id'])) {
                continue;
            }

            $branch = $branches->get((int) $item['branch_id']);
            if (! $branch) {
                continue;
            }

            $isAssignmentActive = (bool) ($item['is_active'] ?? true);
            $branchHoursByDay = collect($branch->opening_hours ?? DayOfWeek::defaultSchedule())
                ->keyBy('day');

            $schedule = is_array($item['schedule'] ?? null) ? $item['schedule'] : [];

            foreach ($schedule as $dayEntry) {
                if (! is_array($dayEntry) || empty($dayEntry['is_open'])) {
                    continue;
                }

                $dayKey = (string) ($dayEntry['day'] ?? '');
                $dayEnum = DayOfWeek::tryFrom($dayKey);
                $dayLabel = $dayEnum?->getLabel() ?? $dayKey;

                $openTime = substr((string) ($dayEntry['open_time'] ?? ''), 0, 5);
                $closeTime = substr((string) ($dayEntry['close_time'] ?? ''), 0, 5);

                if ($openTime === '' || $closeTime === '') {
                    $fail(__('Please specify both start and end times for :day at :branch.', [
                        'day' => $dayLabel,
                        'branch' => $branch->name,
                    ]));

                    continue;
                }

                if ($openTime >= $closeTime) {
                    $fail(__('The end time must be after the start time on :day at :branch.', [
                        'day' => $dayLabel,
                        'branch' => $branch->name,
                    ]));

                    continue;
                }

                $branchDay = $branchHoursByDay->get($dayKey);
                $isBranchOpen = is_array($branchDay) && ! empty($branchDay['is_open']);

                if (! $isBranchOpen) {
                    $fail(__('The collaborator cannot work on :day at :branch because the branch is closed on that day.', [
                        'day' => $dayLabel,
                        'branch' => $branch->name,
                    ]));

                    continue;
                }

                $branchOpenTime = substr((string) ($branchDay['open_time'] ?? ''), 0, 5);
                $branchCloseTime = substr((string) ($branchDay['close_time'] ?? ''), 0, 5);

                if ($branchOpenTime !== '' && $branchCloseTime !== '' && ($openTime < $branchOpenTime || $closeTime > $branchCloseTime)) {
                    $fail(__('The schedule on :day at :branch (:start - :end) must be within the branch opening hours (:branch_start - :branch_end).', [
                        'day' => $dayLabel,
                        'branch' => $branch->name,
                        'start' => $openTime,
                        'end' => $closeTime,
                        'branch_start' => $branchOpenTime,
                        'branch_end' => $branchCloseTime,
                    ]));

                    continue;
                }

                if ($isAssignmentActive) {
                    foreach ($activeDayIntervals[$dayKey] ?? [] as $existingInterval) {
                        if ($openTime < $existingInterval['close_time'] && $existingInterval['open_time'] < $closeTime) {
                            $fail(__('The schedule on :day overlaps between :branch_a (:start_a - :end_a) and :branch_b (:start_b - :end_b).', [
                                'day' => $dayLabel,
                                'branch_a' => $existingInterval['branch_name'],
                                'start_a' => $existingInterval['open_time'],
                                'end_a' => $existingInterval['close_time'],
                                'branch_b' => $branch->name,
                                'start_b' => $openTime,
                                'end_b' => $closeTime,
                            ]));
                        }
                    }

                    $activeDayIntervals[$dayKey][] = [
                        'branch_name' => $branch->name,
                        'open_time' => $openTime,
                        'close_time' => $closeTime,
                    ];
                }
            }
        }
    }
}
