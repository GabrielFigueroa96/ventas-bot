<?php

namespace App\Console\Commands;

use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateTenant extends Command
{
    protected $signature   = 'tenant:migrate {--tenant= : ID del tenant (omitir para migrar todos)}';
    protected $description = 'Corre las migraciones de tenant/ en la DB de cada negocio';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $query = DB::connection('mysql')->table('ia_tenants')->where('activo', true);
        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return 1;
        }

        $manager = app(TenantManager::class);

        foreach ($tenants as $tenant) {
            $this->info("Migrando: {$tenant->nombre}...");

            if (!$manager->loadById($tenant->id)) {
                $this->error("  No se pudo cargar el tenant {$tenant->id}");
                continue;
            }

            $this->call('migrate', [
                '--path'  => 'database/migrations/tenant',
                '--force' => true,
            ]);

            $this->info("  OK");
        }

        return 0;
    }
}
