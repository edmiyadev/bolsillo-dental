<?php

namespace App\Filament\Central\Widgets;

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
        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('is_active', true)->count();
        $totalDomains = Domain::query()->count();

        return [
            Stat::make(__('Total clients'), (string) $totalTenants)
                ->description(__(':count active clients', ['count' => $activeTenants]))
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary'),
            Stat::make(__('Active clients'), (string) $activeTenants)
                ->description(__('Databases provisioned'))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success'),
            Stat::make(__('Configured domains'), (string) $totalDomains)
                ->description(__('Active domains and subdomains'))
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->color('info'),
        ];
    }
}
