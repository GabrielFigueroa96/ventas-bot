<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SetTenantFromUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && $user->tenant_id) {
            $manager = app(TenantManager::class);

            if (!$manager->loadById($user->tenant_id)) {
                Log::error("SetTenantFromUser: tenant {$user->tenant_id} no encontrado para user {$user->id}");
                abort(503, 'Tenant no disponible.');
            }
        }

        return $next($request);
    }
}
