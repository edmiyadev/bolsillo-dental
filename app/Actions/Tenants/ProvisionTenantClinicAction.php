<?php

namespace App\Actions\Tenants;

use App\Data\Tenants\CreateTenantData;
use App\Models\Clinic;
use App\Models\Tenant;

class ProvisionTenantClinicAction
{
    public function handle(Tenant $tenant, CreateTenantData $data): void
    {
        $tenant->run(function () use ($data): void {
            Clinic::query()->updateOrCreate(
                ['name' => $data->name],
                [
                    'email' => $data->email,
                    'phone' => $data->phone,
                    'rnc' => $data->rnc,
                    'address' => $data->address,
                ],
            );
        });
    }
}
