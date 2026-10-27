<?php

namespace App\Filament\Clusters\Settings\Resources\Cubicles\Pages;

use App\Filament\Clusters\Settings\Resources\Cubicles\CubicleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCubicle extends CreateRecord
{
    protected static string $resource = CubicleResource::class;

    /**
     * @return array<mixed>
     */
    public function getSubNavigation(): array
    {
        if (filled($cluster = static::getCluster()) && $cluster::shouldRegisterSubNavigation()) {
            return $this->generateNavigationItems($cluster::getClusteredComponents());
        }

        return [];
    }
}
