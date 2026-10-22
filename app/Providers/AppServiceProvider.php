<?php

namespace App\Providers;

use App\Listeners\SetupVerifiedUser;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Number::useCurrency('DOP');
        Number::useLocale('es_DO');

        Event::listen(Verified::class, SetupVerifiedUser::class);
    }
}
