<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TenantManager
{
    protected ?object $tenant = null;

    /**
     * Activa el tenant a partir del phone_number_id que llega en el webhook de WhatsApp.
     */
    public function loadByPhoneNumberId(string $phoneNumberId): bool
    {
        $tenant = Cache::remember("tenant_phone_{$phoneNumberId}", 300, fn() =>
            DB::connection('mysql')
                ->table('ia_tenants')
                ->where('phone_number_id', trim($phoneNumberId))
                ->where('activo', true)
                ->first()
        );

        if (!$tenant) {
            return false;
        }

        $this->switchConnection($tenant);
        $this->tenant = $tenant;
        return true;
    }

    /**
     * Activa el tenant a partir del webhook_token (usado en la verificación GET del webhook).
     */
    public function loadByWebhookToken(string $token): bool
    {
        $tenant = Cache::remember("tenant_token_{$token}", 300, fn() =>
            DB::connection('mysql')
                ->table('ia_tenants')
                ->where('webhook_token', $token)
                ->where('activo', true)
                ->first()
        );

        if (!$tenant) {
            return false;
        }

        $this->switchConnection($tenant);
        $this->tenant = $tenant;
        return true;
    }

    /**
     * Activa el tenant a partir del page_id que llega en el webhook de Messenger/Instagram.
     */
    public function loadByPageId(string $pageId): bool
    {
        $tenant = Cache::remember("tenant_page_{$pageId}", 300, fn() =>
            DB::connection('mysql')
                ->table('ia_tenants')
                ->where('page_id', $pageId)
                ->where('activo', true)
                ->first()
        );

        if (!$tenant) {
            return false;
        }

        $this->switchConnection($tenant);
        $this->tenant = $tenant;
        return true;
    }

    /**
     * Activa el tenant a partir del ID (usado por el middleware del admin panel).
     */
    public function loadById(int $id): bool
    {
        $tenant = Cache::remember("tenant_id_{$id}", 300, fn() =>
            DB::connection('mysql')
                ->table('ia_tenants')
                ->where('id', $id)
                ->where('activo', true)
                ->first()
        );

        if (!$tenant) {
            return false;
        }

        $this->switchConnection($tenant);
        $this->tenant = $tenant;
        return true;
    }

    public function get(): ?object
    {
        return $this->tenant;
    }

    public function isLoaded(): bool
    {
        return $this->tenant !== null;
    }

    private function switchConnection(object $tenant): void
    {
        // Cambiar la conexión a la DB del tenant
        config([
            'database.connections.tenant.driver'    => 'mysql',
            'database.connections.tenant.host'      => $tenant->db_host,
            'database.connections.tenant.port'      => '3306',
            'database.connections.tenant.database'  => $tenant->db_name,
            'database.connections.tenant.username'  => $tenant->db_user,
            'database.connections.tenant.password'  => $tenant->db_password,
            'database.connections.tenant.charset'   => 'utf8mb4',
            'database.connections.tenant.collation' => 'utf8mb4_unicode_ci',
            'database.connections.tenant.prefix'    => '',
            'database.connections.tenant.strict'    => true,
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');

        // Setear las credenciales de API de este tenant para que BotService las lea con config()
        config([
            'api.whatsapp.key'             => $tenant->whatsapp_api_key ?? null,
            'api.whatsapp.phone_number_id' => $tenant->phone_number_id ?? null,
            'api.openai.key'               => $tenant->openai_api_key,
            'api.messenger.page_id'        => $tenant->page_id ?? null,
            'api.messenger.token'          => $tenant->messenger_token ?? null,
            'api.messenger.fb_page_id'     => $tenant->messenger_page_id ?? $tenant->page_id ?? null,
        ]);
    }
}
