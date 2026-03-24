<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $nro
 * @property string $nomcli
 * @property float  $cant
 * @property string $descrip
 * @property float  $kilos
 * @property float  $precio
 * @property float  $NETO
 * @property int    $codcli
 */
class Vmayo extends Model
{
    protected $table = 'vmayo';
    public $timestamps = false;

    protected $fillable = ['nro', 'nomcli', 'cant', 'descrip', 'kilos', 'precio', 'NETO', 'codcli'];
}
