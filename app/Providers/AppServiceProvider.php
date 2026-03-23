<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Models\IaEmpresa;
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
            try {
                $iaEmpresa  = IaEmpresa::select('imagen_tienda', 'updated_at')->first();
                $logoTienda = $iaEmpresa?->imagen_tienda;
                $logoTs     = $iaEmpresa?->updated_at?->timestamp;
            } catch (\Throwable) {
                $logoTienda = null;
                $logoTs     = null;
            }
            $view->with('empresaNombre', $nombre);
            $view->with('logoTienda', $logoTienda);
            $view->with('logoTs', $logoTs);
        });
    }
}
