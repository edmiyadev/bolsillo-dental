<?php

namespace App\Actions\Tenants;

use App\Data\Tenants\CreateTenantData;
use App\Models\Tenant;

class CreateTenantAction
{
    public function __construct(
        private ProvisionTenantAdminUserAction $provisionTenantAdminUser,
    ) {}

    public function handle(CreateTenantData $data): Tenant
    {
        $tenant = Tenant::query()->create([
            'id' => $data->id,
            'name' => $data->name,
            'email' => $data->email,
            'is_active' => $data->isActive,
            'tenancy_db_name' => $data->databaseName,
        ]);

        foreach ($data->domains as $domain) {
            $tenant->domains()->create([
                'domain' => $domain,
            ]);
        }

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
