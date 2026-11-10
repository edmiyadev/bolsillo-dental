<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyByDomainOrSubdomain;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Tenant routes are handled by the Filament AdminPanelProvider (path: '/').
| Any additional custom web routes for tenants can be registered here.
|
*/

Route::middleware([
    InitializeTenancyByDomainOrSubdomain::class,
    'web',
])->group(function (): void {
    Route::get('/impersonate/{token}', function (string $token) {
        return UserImpersonation::makeResponse($token);
    })->name('tenant.impersonate');
});

