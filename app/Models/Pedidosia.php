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
        'vmayo_nro',
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
    const ESTADO_EN_REPARTO  = 3;
    const ESTADO_ENTREGADO   = 4;
    const ESTADO_CANCELADO   = 9;

    /** @deprecated */
    const ESTADO_FINALIZADO  = 1;

    const ESTADOS = [
        0 => ['label' => 'Pendiente',   'css' => 'bg-yellow-100 text-yellow-700'],
        1 => ['label' => 'Confirmado',  'css' => 'bg-blue-100 text-blue-700'],
        2 => ['label' => 'Preparado',   'css' => 'bg-orange-100 text-orange-700'],
        3 => ['label' => 'En reparto',  'css' => 'bg-indigo-100 text-indigo-700'],
        4 => ['label' => 'Entregado',   'css' => 'bg-green-100 text-green-700'],
        9 => ['label' => 'Cancelado',   'css' => 'bg-red-100 text-red-600'],
    ];

    const MENSAJES_ESTADO = [
        1 => "✅ Tu pedido #{nro} fue *confirmado* y está siendo preparado. ¡Gracias!",
        2 => "📦 Tu pedido #{nro} está *preparado*. ¡Pronto lo recibirás!",
        3 => "✅ Tu pedido #{nro} fue *entregado*. ¡Gracias por tu compra!",
        9 => "❌ Tu pedido #{nro} fue *cancelado*. Si tenés alguna consulta, escribinos.",
    ];

    /**
     * Estado máximo según tipo de entrega.
     * Retiro: 0→1→2→3 (Retirado)
     * Envío:  0→1→2→3→4 (En reparto → Entregado)
     */
    public function estadoMax(): int
    {
        return $this->tipo_entrega === 'retiro'
            ? self::ESTADO_EN_REPARTO   // 3 = "Retirado" para retiro
            : self::ESTADO_ENTREGADO;   // 4 = "Entregado" para envío
    }

    public function estadoLabel(): string
    {
        if ($this->tipo_entrega === 'retiro') {
            return match((int) $this->estado) {
                self::ESTADO_EN_CAMINO  => 'Listo para retirar',
                self::ESTADO_EN_REPARTO => 'Retirado',
                default => self::ESTADOS[$this->estado]['label'] ?? '—',
            };
        }
        return self::ESTADOS[$this->estado]['label'] ?? '—';
    }

    public function estadoCss(): string
    {
        if ($this->tipo_entrega === 'retiro' && (int) $this->estado === self::ESTADO_EN_REPARTO) {
            return 'bg-green-100 text-green-700';
        }
        return self::ESTADOS[$this->estado]['css'] ?? 'bg-gray-100 text-gray-600';
    }

    public function esEstadoNotificable(): bool
    {
        $estados = $this->tipo_entrega === 'retiro'
            ? [self::ESTADO_CONFIRMADO, self::ESTADO_EN_CAMINO, self::ESTADO_CANCELADO]
            : [self::ESTADO_CONFIRMADO, self::ESTADO_EN_REPARTO, self::ESTADO_ENTREGADO, self::ESTADO_CANCELADO];

        return in_array((int) $this->estado, $estados);
    }

    public function mensajeParaEstado(int $estado): string
    {
        if ($estado === self::ESTADO_EN_CAMINO || $estado === self::ESTADO_ENTREGADO) {
            $detalle = $this->detalleVmayo();

            if ($estado === self::ESTADO_EN_CAMINO) {
                if ($this->tipo_entrega === 'retiro') {
                    return "🏪 Tu pedido #{$this->nro} está *listo para retirar*. ¡Te esperamos!{$detalle}";
                }
                return "📦 Tu pedido #{$this->nro} está *preparado* y próximamente saldrá para entrega.{$detalle}";
            }

            // ESTADO_ENTREGADO
            return "✅ Tu pedido #{$this->nro} fue *entregado*. ¡Gracias por tu compra!{$detalle}";
        }
        if ($estado === self::ESTADO_EN_REPARTO) {
            if ($this->tipo_entrega === 'retiro') {
                return "✅ Tu pedido #{$this->nro} fue *retirado*. ¡Gracias por tu compra!";
            }
            return "🚚 Tu pedido #{$this->nro} está *en camino*. ¡Ya salió para entrega!";
        }
        $plantilla = self::MENSAJES_ESTADO[$estado] ?? '';
        return str_replace('{nro}', $this->nro, $plantilla);
    }

    private function detalleVmayo(): string
    {
        if (!$this->vmayo_nro) return '';

        $items = Vmayo::where('nro', $this->vmayo_nro)->get();
        if ($items->isEmpty()) return '';

        $lineas = $items->map(function ($v) {
            if ($v->cant > 0 && $v->kilos > 0) {
                $cant = (int) $v->cant . 'u · ' . number_format($v->kilos, 3, ',', '.') . 'kg';
            } elseif ($v->kilos > 0) {
                $cant = number_format($v->kilos, 3, ',', '.') . ' kg';
            } else {
                $cant = (int) $v->cant . ' u';
            }
            return "• {$v->descrip}: {$cant} — $" . number_format($v->NETO, 2, ',', '.');
        })->implode("\n");

        $total = number_format($items->sum('NETO'), 2, ',', '.');
        return "\n\n*Detalle:*\n{$lineas}\n*Total: \${$total}*";
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
