<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Hashids\Hashids;
use App\Models\User;
use App\Models\Team;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('hashid', function () {
            return new Hashids(config('app.key'), 8);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Implicitly grant all permissions to the Super Admin role
         * and allow the Super Admin role to bypass all policies.
         */
        Gate::after(function (User $user, string $ability) {
            return $user->hasRole('Super Admin');
        });

        Route::bind('user_hashid', function ($value) {
            return User::findOrFailByHashid($value);
        });

        Route::bind('team_hashid', function ($value) {
            return Team::findOrFailByHashid($value);
        });
    }
}
