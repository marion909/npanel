<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Observers\DomainObserver;
use App\Observers\SubdomainObserver;

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
        Vite::prefetch(concurrency: 3);
        Domain::observe(DomainObserver::class);
        Subdomain::observe(SubdomainObserver::class);
    }
}
