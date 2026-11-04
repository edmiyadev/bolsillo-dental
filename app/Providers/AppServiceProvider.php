<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use App\Listeners\SetupVerifiedUser;
use App\Models\Role;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        Gate::before(function ($user, $ability, array $arguments = []) {
            $target = $arguments[0] ?? null;

            if ($target instanceof Role && $target->name === RoleEnum::SuperAdmin->value && in_array($ability, ['update', 'delete', 'forceDelete'], true)) {
                return false;
            }

            return method_exists($user, 'hasRole') && $user->hasRole(RoleEnum::SuperAdmin->value) ? true : null;
        });

        Event::listen(Verified::class, SetupVerifiedUser::class);

        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }
    }
}
