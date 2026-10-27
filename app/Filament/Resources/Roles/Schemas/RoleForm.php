<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\PermissionOperationsEnum;
use App\Models\Role;
use App\Support\ModelHelper;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $allPermissions = rescue(fn () => Permission::all(), collect());

        return $schema
            ->components([
                Section::make()
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->unique(ignoreRecord: true),
                        Section::make()
                            ->label(__('Permissions'))
                            ->columnSpanFull()
                            ->schema(
                                collect(ModelHelper::getModelsWithExclusions())
                                    ->map(function (string $modelName) use ($allPermissions) {
                                        $module = Str::snake(Str::pluralStudly($modelName));
                                        $options = [];

                                        foreach (PermissionOperationsEnum::cases() as $op) {
                                            $permissionName = "{$op->value}_{$module}";
                                            $perm = $allPermissions->firstWhere('name', $permissionName);
                                            if ($perm !== null) {
                                                $options[$perm->id] = $op->getLabel();
                                            }
                                        }

                                        return Section::make([
                                            CheckboxList::make("permissions_{$module}")
                                                ->label(__(Str::headline(Str::pluralStudly($modelName))))
                                                ->options($options)
                                                ->bulkToggleable()
                                                ->columns(4)
                                                ->dehydrated(false)
                                                ->loadStateFromRelationshipsUsing(function (CheckboxList $component, ?Role $record) use ($options): void {
                                                    if ($record === null) {
                                                        return;
                                                    }

                                                    $assignedIds = $record->permissions
                                                        ->whereIn('id', array_keys($options))
                                                        ->pluck('id')
                                                        ->toArray();

                                                    $component->state($assignedIds);
                                                })
                                                ->saveRelationshipsUsing(function (Role $record, ?array $state) use ($options): void {
                                                    $state = collect($state ?? [])->map(fn ($id) => (int) $id)->toArray();

                                                    $record->permissions()->detach(array_keys($options));

                                                    if (! empty($state)) {
                                                        $record->permissions()->attach($state);
                                                    }
                                                }),
                                        ]);
                                    })->toArray()),
                    ]),
            ]);
    }
}
