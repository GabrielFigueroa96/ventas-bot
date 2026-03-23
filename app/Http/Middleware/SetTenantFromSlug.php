<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;

class SetTenantFromSlug
{
    public function handle(Request $request, Closure $next)
    {
        $slug = $request->route('slug');

        if (!$slug) {
            abort(404);
        }

        $manager = app(TenantManager::class);

        if (!$manager->loadBySlug($slug)) {
            abort(404, 'Tienda no encontrada.');
        }

        return $next($request);
    }
}
