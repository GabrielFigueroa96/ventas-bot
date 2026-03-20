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
        2 => ['label' => 'En camino',   'css' => 'bg-orange-100 text-orange-700'],
        3 => ['label' => 'Entregado',   'css' => 'bg-green-100 text-green-700'],
    ];

    const MENSAJES_ESTADO = [
        1 => "✅ Tu pedido #{nro} fue *confirmado* y está siendo preparado. ¡Gracias!",
        2 => "🚚 Tu pedido #{nro} está *en camino*. ¡Pronto lo recibirás!",
        3 => "📦 Tu pedido #{nro} fue *entregado*. ¡Gracias por tu compra!",
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'idcliente');
    }

    public function items()
    {
        return $this->hasMany(Pedido::class, 'nro', 'nro');
    }
}
