<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoNotificacion extends Model
{
    protected $table      = 'ia_pedido_notificaciones';
    public    $timestamps = false;

    protected $fillable = ['nro', 'pv', 'estado_notificado', 'phone', 'enviado_at'];
}
