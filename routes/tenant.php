<?php

declare(strict_types=1);

use App\Http\Middleware\InitializeTenancyByDomainOrSubdomain;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Tenant routes are handled by the Filament AdminPanelProvider (path: '/').
| Any additional custom web routes for tenants can be registered here.
|
*/

Route::middleware([InitializeTenancyByDomainOrSubdomain::class])
    ->get('/tenancy/assets/{path}', function (string $path) {
        $publicPath = storage_path("app/public/{$path}");
        if (file_exists($publicPath)) {
            return response()->file($publicPath);
        }

        $privatePath = storage_path("app/{$path}");
        if (file_exists($privatePath)) {
            return response()->file($privatePath);
        }

        abort(404);
    })
    ->where('path', '(.*)')
    ->name('stancl.tenancy.asset');
