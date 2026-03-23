<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidosia extends Model
{
    protected $table = 'ia_pedidos';

    protected $fillable = [
        'nro',
        'codcli',
        'idcliente',
        'nomcli',
        'fecha',
        'tipo_entrega',
        'calle',
        'numero',
        'localidad',
        'dato_extra',
        'forma_pago',
        'total',
        'obs',
        'estado',
        'pedido_at',
    ];

    public function getDireccionAttribute(): ?string
    {
        if (!$this->calle) return null;
        $dir = trim("{$this->calle} {$this->numero}");
        if ($this->localidad) $dir .= ", {$this->localidad}";
        if ($this->dato_extra) $dir .= " ({$this->dato_extra})";
        return $dir;
    }

    protected $casts = [
        'fecha'     => 'date',
        'pedido_at' => 'datetime',
        'total'     => 'float',
    ];

    const ESTADO_PENDIENTE   = 0;
    const ESTADO_CONFIRMADO  = 1;
    const ESTADO_EN_CAMINO   = 2;
    const ESTADO_ENTREGADO   = 3;

    /** @deprecated */
    const ESTADO_FINALIZADO  = 1;

    const ESTADOS = [
        0 => ['label' => 'Pendiente',   'css' => 'bg-yellow-100 text-yellow-700'],
        1 => ['label' => 'Confirmado',  'css' => 'bg-blue-100 text-blue-700'],
        2 => ['label' => 'Preparado',   'css' => 'bg-orange-100 text-orange-700'],
        3 => ['label' => 'Entregado',   'css' => 'bg-green-100 text-green-700'],
    ];

    const MENSAJES_ESTADO = [
        1 => "✅ Tu pedido #{nro} fue *confirmado* y está siendo preparado. ¡Gracias!",
        2 => "📦 Tu pedido #{nro} está *preparado*. ¡Pronto lo recibirás!",
        3 => "✅ Tu pedido #{nro} fue *entregado*. ¡Gracias por tu compra!",
    ];

    /**
     * Devuelve el label del estado según tipo de entrega.
     * Para retiro: estado 2 = "Listo para retirar", estado 3 = "Retirado"
     * Para envío:  estado 2 = "Preparado",          estado 3 = "Entregado"
     */
    public function estadoLabel(): string
    {
        if ($this->tipo_entrega === 'retiro') {
            return match((int) $this->estado) {
                self::ESTADO_EN_CAMINO  => 'Listo para retirar',
                self::ESTADO_ENTREGADO  => 'Retirado',
                default => self::ESTADOS[$this->estado]['label'] ?? '—',
            };
        }
        return self::ESTADOS[$this->estado]['label'] ?? '—';
    }

    public function estadoCss(): string
    {
        return self::ESTADOS[$this->estado]['css'] ?? 'bg-gray-100 text-gray-600';
    }

    /**
     * Mensaje WhatsApp para el cliente al avanzar de estado.
     */
    public function mensajeParaEstado(int $estado): string
    {
        if ($estado === self::ESTADO_EN_CAMINO) {
            if ($this->tipo_entrega === 'retiro') {
                return "🏪 Tu pedido #{$this->nro} está *listo para retirar*. ¡Te esperamos!";
            }
            return "📦 Tu pedido #{$this->nro} está *preparado* y listo para el envío.";
        }
        if ($estado === self::ESTADO_ENTREGADO) {
            if ($this->tipo_entrega === 'retiro') {
                return "✅ Tu pedido #{$this->nro} fue *retirado*. ¡Gracias por tu compra!";
            }
        }
        $plantilla = self::MENSAJES_ESTADO[$estado] ?? '';
        return str_replace('{nro}', $this->nro, $plantilla);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'idcliente');
    }

    public function items()
    {
        return $this->hasMany(Pedido::class, 'nro', 'nro');
    }
}
