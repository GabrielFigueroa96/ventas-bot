<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Services\TenantManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        View::composer('admin.*', function ($view) {
            try {
                $nombre = Empresa::value('nombre') ?? 'Panel Admin';
            } catch (\Throwable) {
                $nombre = 'Panel Admin';
            }
            $view->with('empresaNombre', $nombre);
        });
    }
}
