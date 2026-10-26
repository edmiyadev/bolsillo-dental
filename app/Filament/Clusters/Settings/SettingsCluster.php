<?php

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('Settings');
    }
}
