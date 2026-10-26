<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Settings\Pages\EditClinicProfile;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\InitializeTenancyByDomainOrSubdomain;
use App\Models\Clinic;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/')
            ->brandName(fn (): string => tenancy()->initialized
                ? (Clinic::query()->first()?->name ?? (string) config('app.name', 'Bolsillo Dental'))
                : (string) config('app.name', 'Bolsillo Dental'))
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->emailChangeVerification()
            ->profile(isSimple: false)
            ->userMenuItems([
                Action::make('clinic_profile')
                    ->label(fn (): string => __('Clinic profile'))
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->url(fn (): string => EditClinicProfile::getUrl()),
            ])
            ->colors([
                'primary' => Color::Cyan,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('10')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
            ])
            ->middleware([
                InitializeTenancyByDomainOrSubdomain::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
