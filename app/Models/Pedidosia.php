<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedidosia extends Model
{
    protected $table = 'pedidosia';

    protected $fillable = [
        'nro',
        'codcli',
        'idcliente',
        'nomcli',
        'fecha',
        'tipo_entrega',
        'forma_pago',
        'total',
        'obs',
        'estado',
        'pedido_at',
    ];

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
