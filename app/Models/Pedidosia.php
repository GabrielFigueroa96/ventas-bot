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

    const ESTADO_PENDIENTE  = 0;
    const ESTADO_FINALIZADO = 1;

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'idcliente');
    }

    public function items()
    {
        return $this->hasMany(Pedido::class, 'nro', 'nro');
    }
}
