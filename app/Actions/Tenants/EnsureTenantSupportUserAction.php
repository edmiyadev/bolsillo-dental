<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\RoleEnum;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnsureTenantSupportUserAction
{
    public function handle(Tenant $tenant): User
    {
        $email = (string) (config('tenancy.support_user.email') ?: 'soporte@bolsillodental.com');
        $name = (string) (config('tenancy.support_user.name') ?: 'Soporte Bolsillo Dental');

        return $tenant->run(function () use ($name, $email): User {
            $user = User::query()->where('email', $email)->first();

            if (! $user instanceof User) {
                $emptyUser = User::query()->where('email', '')->orWhereNull('email')->first();

                if ($emptyUser instanceof User) {
                    $emptyUser->forceFill([
                        'name' => $name,
                        'email' => $email,
                    ])->save();
                    $user = $emptyUser;
                } else {
                    $user = User::query()->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(Str::random(40)),
                        'email_verified_at' => now(),
                    ]);
                }
            } else {
                if (blank($user->name)) {
                    $user->forceFill(['name' => $name])->save();
                }
            }

            if (! $user->hasVerifiedEmail()) {
                $user->forceFill([
                    'email_verified_at' => now(),
                ])->save();
            }

            if (! $user->hasRole(RoleEnum::Admin->value)) {
                $user->assignRole(RoleEnum::Admin->value);
            }

            return $user;
        });
    }
}
