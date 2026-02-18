<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
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
        //
        //  $this->routes(function () {

        //     Route::middleware('api')
        //         ->prefix('api')
        //         ->group(base_path('routes/api/user.php'));

        //     Route::middleware('api')
        //         ->prefix('api')
        //         ->group(base_path('routes/api/coach.php'));

        // });
    }
}
