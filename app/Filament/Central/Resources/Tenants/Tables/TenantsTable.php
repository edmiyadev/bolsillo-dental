<?php

namespace App\Filament\Central\Resources\Tenants\Tables;

use App\Actions\Tenants\EnsureTenantSupportUserAction;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Livewire\Component;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Clinic name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('id')
                    ->label(__('Identifier (Slug)'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('database_name')
                    ->label(__('Database name'))
                    ->state(fn (Tenant $record): string => $record->databaseName())
                    ->badge()
                    ->color('info'),
                TextColumn::make('domains.domain')
                    ->label(__('Domains'))
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Clinic email'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label(__('Client'))
                    ->relationship('client', 'name'),
                TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label(__('Connect as support'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Connect as Technical Support'))
                    ->modalDescription(__('You are about to start an administrative session on this clinic\'s panel using the dedicated technical support account. This will not affect or impersonate any clinic staff account.'))
                    ->modalSubmitActionLabel(__('Connect'))
                    ->modalIcon(Heroicon::OutlinedShieldCheck)
                    ->visible(fn (Tenant $record): bool => (bool) $record->is_active && $record->domains->isNotEmpty())
                    ->action(function (Tenant $record, EnsureTenantSupportUserAction $ensureSupportUser, Component $livewire): void {
                        $baseUrl = static::resolveTenantBaseUrl($record);

                        if (blank($baseUrl)) {
                            Notification::make()
                                ->title(__('No domain assigned to this clinic.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $supportUser = $ensureSupportUser->handle($record);

                        $token = tenancy()->impersonate(
                            $record,
                            (string) $supportUser->id,
                            $baseUrl,
                            'web',
                        );

                        $url = "{$baseUrl}/impersonate/{$token->token}";

                        $livewire->js("window.open('{$url}', '_blank');");
                    }),
                Action::make('open_domain')
                    ->label(__('Open panel'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Tenant $record): ?string => static::resolveTenantBaseUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Tenant $record): bool => $record->domains->isNotEmpty()),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function resolveTenantBaseUrl(Tenant $record): ?string
    {
        $domain = $record->domains->first()?->domain;

        if (blank($domain)) {
            return null;
        }

        if (! str_contains($domain, '.')) {
            /** @var array<int, string> $centralDomains */
            $centralDomains = config('tenancy.central_domains', ['localhost']);
            $centralDomain = reset($centralDomains) ?: 'localhost';
            $domain = "{$domain}.{$centralDomain}";
        }

        $scheme = request()->getScheme() ?: 'http';
        $port = request()->getPort();
        $portSuffix = ($port && ! in_array($port, [80, 443], true)) ? ':'.$port : '';

        return "{$scheme}://{$domain}{$portSuffix}";
    }
}

