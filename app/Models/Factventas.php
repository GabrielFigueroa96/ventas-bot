<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $fecha
 * @property int    $nro
 * @property string $nomcli
 * @property float  $cant
 * @property string $descrip
 * @property float  $kilos
 * @property float  $precio
 * @property float  $neto
 * @property string $codigo
 * @property string $tipo
 * @property string $fact
 * @property string $pv
 */
class Factventas extends Model
{
    protected $table = 'factventas';
    public $timestamps = false;

    protected $fillable = [
        'fecha', 'nro', 'nomcli', 'cant', 'descrip',
        'kilos', 'precio', 'NETO', 'codigo', 'tipo', 'fact', 'pv', 'venta'
    ];

    // Solo registros de tipo venta
    protected static function booted(): void
    {
        static::addGlobalScope('ventas', fn($q) => $q->where('tipo', 'VE'));
    }
}
