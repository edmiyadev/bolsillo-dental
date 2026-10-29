<?php

namespace App\Filament\Clusters\Settings\Resources\Branches\Pages;

use App\Filament\Clusters\Settings\Resources\Branches\BranchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => (bool) ($this->record->clinic && $this->record->clinic->branches()->count() <= 1)),
            ForceDeleteAction::make()
                ->hidden(fn (): bool => (bool) ($this->record->clinic && $this->record->clinic->branches()->count() <= 1)),
            RestoreAction::make(),
        ];
    }
}
