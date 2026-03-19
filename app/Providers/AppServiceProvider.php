<?php

namespace App\Providers;

use App\Services\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una única instancia de TenantManager por request
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        //
    }
}
