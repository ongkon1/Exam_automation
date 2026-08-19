<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // The paginator defaults to Tailwind markup, but this app is styled with
        // Bootstrap 5 — without this, every paginated list renders unstyled.
        Paginator::useBootstrapFive();
    }
}
