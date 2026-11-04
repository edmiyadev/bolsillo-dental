<?php

namespace App\Actions\Tenants;

use App\Actions\Services\CreateDefaultServicesForUserAction;
use App\Enums\RoleEnum;
use App\Models\Tenant;
use App\Models\User;

class ProvisionTenantAdminUserAction
{
    public function __construct(
        private CreateDefaultServicesForUserAction $createDefaultServices,
    ) {}

    public function handle(Tenant $tenant, string $name, string $email, string $password): void
    {
        $tenant->run(function () use ($name, $email, $password): void {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                ],
            );

            if (! $user->hasVerifiedEmail()) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            $user->syncRoles([RoleEnum::Admin->value]);

            $this->createDefaultServices->handle($user);
        });
    }
}
