<?php

namespace App\Filament\Central\Resources\Tenants\Pages;

use App\Actions\Tenants\CreateTenantAction;
use App\Data\Tenants\CreateTenantData;
use App\Filament\Central\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantAction::class)->handle(
            CreateTenantData::fromArray($data),
        );
    }
}
