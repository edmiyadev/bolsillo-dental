<?php

namespace App\Filament\Central\Resources\Tenants\Schemas;

use App\Models\Domain;
use App\Models\Tenant;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Client & Database Details'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Client name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                                if ($operation !== 'create' || blank($state)) {
                                    return;
                                }

                                $slug = Str::slug($state);

                                if (blank($get('id'))) {
                                    $set('id', $slug);
                                }

                                if (blank($get('tenancy_db_name'))) {
                                    $prefix = (string) config('tenancy.database.prefix', 'bolsillo_tenant_');
                                    $set('tenancy_db_name', $prefix.Str::replace('-', '_', $slug));
                                }

                                if (blank($get('domain'))) {
                                    $set('domain', $slug.'.localhost');
                                }
                            }),
                        TextInput::make('id')
                            ->label(__('Identifier (Slug)'))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(64)
                            ->alphaDash()
                            ->unique(table: Tenant::class, column: 'id', ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('email')
                            ->label(__('Contact email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('tenancy_db_name')
                            ->label(__('Database name'))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(63)
                            ->regex('/^[a-zA-Z0-9_.-]+$/')
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                            ->helperText(__('Only letters, numbers, and underscores. Created and migrated automatically.')),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Domains & Subdomains'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('domain')
                            ->label(__('Primary domain'))
                            ->placeholder('clinica-sonrisa.localhost')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->unique(table: Domain::class, column: 'domain'),
                        Repeater::make('domains')
                            ->label(__('Domains'))
                            ->relationship('domains')
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->schema([
                                TextInput::make('domain')
                                    ->label(__('Domain'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(table: Domain::class, column: 'domain', ignoreRecord: true),
                            ])
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),

                Section::make(__('Initial Client Administrator'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Toggle::make('create_initial_user')
                            ->label(__('Create initial user with default services'))
                            ->default(true)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('admin_name')
                            ->label(__('Administrator name'))
                            ->visible(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->required(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->maxLength(255),
                        TextInput::make('admin_email')
                            ->label(__('Administrator email'))
                            ->email()
                            ->visible(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->required(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->maxLength(255),
                        TextInput::make('admin_password')
                            ->label(__('Administrator password'))
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->required(fn (Get $get): bool => (bool) $get('create_initial_user'))
                            ->minLength(8),
                    ]),
            ]);
    }
}
