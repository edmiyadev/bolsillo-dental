<?php

namespace App\Filament\Clusters\Settings\Resources\Cubicles\Pages;

use App\Filament\Clusters\Settings\Resources\Cubicles\CubicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCubicles extends ListRecords
{
    protected static string $resource = CubicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
