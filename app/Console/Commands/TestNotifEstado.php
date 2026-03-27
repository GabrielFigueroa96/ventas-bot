<?php

namespace App\Console\Commands;

use App\Models\IaEmpresa;
use App\Models\Pedidosia;
use App\Services\BotService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestNotifEstado extends Command
{
    protected $signature = 'notif:test-estado
                            {phone : Número de WhatsApp destino (ej: 5493415551234)}
                            {--estado=1 : Estado a simular (1=Confirmado, 2=Preparado, 3=En reparto, 4=Entregado, 9=Cancelado)}
                            {--tipo=envio : Tipo de entrega (envio o retiro)}
                            {--nro=999 : Número de pedido de prueba}
                            {--nombre=Cliente Prueba : Nombre del cliente}';

    protected $description = 'Envía una notificación de estado de pedido de prueba para verificar el template';

    public function handle(): void
    {
        // Cargar el primer tenant
        $tenant = DB::connection('mysql')->table('ia_tenants')->where('activo', true)->first();
        if (!$tenant) {
            $this->error('No hay tenants activos.');
            return;
        }
        app(TenantManager::class)->loadById($tenant->id);

        $phone    = $this->argument('phone');
        $estado   = (int) $this->option('estado');
        $tipo     = $this->option('tipo');
        $nro      = (int) $this->option('nro');
        $nombre   = $this->option('nombre');

        $template = IaEmpresa::value('notif_estado_template');

        $this->line('');
        $this->line("📋 <comment>Configuración de prueba:</comment>");
        $this->line("   Tenant:    {$tenant->nombre}");
        $this->line("   Teléfono:  {$phone}");
        $this->line("   Estado:    {$estado} (" . (Pedidosia::ESTADOS[$estado]['label'] ?? '?') . ")");
        $this->line("   Tipo:      {$tipo}");
        $this->line("   Pedido nro: #{$nro}");
        $this->line("   Template:  " . ($template ?: '<fg=yellow>No configurado — usará texto libre</>'));
        $this->line('');

        // Armar un Pedidosia en memoria para usar mensajeParaEstado()
        $pedido = new Pedidosia([
            'nro'          => $nro,
            'nomcli'       => $nombre,
            'tipo_entrega' => $tipo,
            'estado'       => $estado,
        ]);

        $mensaje = $pedido->mensajeParaEstado($estado);

        if (!$mensaje) {
            $this->warn("El estado {$estado} no genera mensaje para tipo '{$tipo}'.");
            return;
        }

        $this->line("💬 <comment>Mensaje a enviar:</comment>");
        $this->line("   Nombre:  {$nombre}");
        $this->line("   Mensaje: {$mensaje}");
        $this->line('');

        if (!$this->confirm('¿Enviar ahora?', true)) {
            $this->line('Cancelado.');
            return;
        }

        try {
            app(BotService::class)->enviarNotifEstadoPedido($phone, $nombre, $mensaje);
            $this->info("✅ Notificación enviada a {$phone}");
        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
    }
}
