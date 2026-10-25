<?php

namespace App\Actions\Tenants;

use App\Data\Tenants\CreateTenantData;
use App\Models\Tenant;

class CreateTenantAction
{
    public function __construct(
        private ProvisionTenantClinicAction $provisionTenantClinic,
        private ProvisionTenantAdminUserAction $provisionTenantAdminUser,
    ) {}

    public function handle(CreateTenantData $data): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => $data->id,
            'client_id' => $data->clientId,
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'rnc' => $data->rnc,
            'address' => $data->address,
            'is_active' => $data->isActive,
            'tenancy_db_name' => $data->databaseName,
        ]);

        foreach ($data->domains as $domain) {
            $tenant->domains()->create([
                'domain' => $domain,
            ]);
        }

        $this->provisionTenantClinic->handle(
            tenant: $tenant,
            data: $data,
        );

        if (
            $data->createInitialUser
            && filled($data->adminName)
            && filled($data->adminEmail)
            && filled($data->adminPassword)
        ) {
            $this->provisionTenantAdminUser->handle(
                tenant: $tenant,
                name: $data->adminName,
                email: $data->adminEmail,
                password: $data->adminPassword,
            );
        }

        return $tenant;
    }
}
