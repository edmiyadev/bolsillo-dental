<?php

namespace App\Listeners;

use Spatie\Permission\PermissionRegistrar;

class ResetPermissionCache
{
    public function handle(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
