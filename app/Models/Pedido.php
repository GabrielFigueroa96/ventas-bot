<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $reg
 * @property int    $nro
 * @property string $fecha
 * @property string $nomcli
 * @property int    $cant
 * @property string $descrip
 * @property float  $kilos
 * @property string $codigo
 * @property int    $codcli
 * @property int    $estado
 * @property string $fecfin
 * @property string $obs
 * @property string $suc
 * @property string $pv
 * @property float  $venta
 * @property-read string $estado_texto
 */
class Pedido extends Model
{
    protected $table = 'pedidos';
    protected $primaryKey = 'reg';

    // La tabla usa 'fecha' en lugar de timestamps de Laravel
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'nro',
        'nomcli',
        'cant',
        'descrip',
        'kilos',
        'precio',
        'neto',
        'codigo',
        'codcli',
        'estado',
        'fecfin',
        'obs',
        'pedido_at',
        'suc',
        'pv',
        'venta',
    ];

    public const ESTADO_PENDIENTE  = 0;
    public const ESTADO_FINALIZADO = 1;
    public const ESTADO_CANCELADO  = 9;

    public function getEstadoTextoAttribute(): string
    {
        return match((int) $this->getAttribute('estado')) {
            self::ESTADO_FINALIZADO => 'Finalizado',
            self::ESTADO_CANCELADO  => 'Cancelado',
            default                 => 'Pendiente',
        };
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'codcli', 'id');
    }

    // Devuelve los renglones de factventas que corresponden a este pedido (venta = nro en factventas)
    public function factventas()
    {
        return Factventas::where('nro', $this->getAttribute('venta'))
            ->where('pv', $this->getAttribute('pv'))
            ->get();
    }
}
