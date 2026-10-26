<?php

namespace App\Filament\Central\Widgets;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Tenant;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantsOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $totalClients = Client::query()->count();
        $activeClients = Client::query()->where('is_active', true)->count();
        $activeTenants = Tenant::query()->where('is_active', true)->count();
        $totalDomains = Domain::query()->count();

        return [
            Stat::make(__('Total clients'), (string) $totalClients)
                ->description(__(':count active clients', ['count' => $activeClients]))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary'),
            Stat::make(__('Active clinics'), (string) $activeTenants)
                ->description(__('Databases provisioned'))
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('success'),
            Stat::make(__('Configured domains'), (string) $totalDomains)
                ->description(__('Active domains and subdomains'))
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->color('info'),
        ];
    }
}
