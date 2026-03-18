<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $nombre
 * @property string $domicilio
 * @property string $prov
 */
class Empresa extends Model
{
    protected $table = 'empresa';
    public $timestamps = false;

    protected $fillable = [
        'bot_info', 'bot_instrucciones',
        'bot_dias_reparto', 'bot_permite_retiro', 'bot_permite_envio', 'bot_medios_pago',
    ];

    protected $casts = [
        'bot_dias_reparto' => 'array',
        'bot_permite_retiro' => 'boolean',
        'bot_permite_envio'  => 'boolean',
        'bot_medios_pago'    => 'array',
    ];

    const DIAS_LABEL = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes',
        3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado',
    ];

    const MEDIOS_PAGO = [
        'efectivo'         => 'Efectivo',
        'transferencia'    => 'Transferencia',
        'cuenta_corriente' => 'Cuenta corriente',
        'otro'             => 'Otro',
    ];
}
