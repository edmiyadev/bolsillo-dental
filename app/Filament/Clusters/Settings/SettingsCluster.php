<?php

namespace App\Filament\Clusters\Settings;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Settings;
    }

    public static function getNavigationLabel(): string
    {
        return __('General');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('General');
    }
}
