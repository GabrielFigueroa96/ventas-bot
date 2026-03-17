<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $cod
 * @property string $nom
 * @property string $dom
 * @property string $loca
 * @property string $prov
 */
class Cuenta extends Model
{
    protected $table      = 'cuentas';
    protected $primaryKey = 'cod';
    public    $keyType    = 'string';
    public    $timestamps = false;

    protected $fillable = ['cod', 'nom', 'dom', 'loca', 'prov'];

    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'cuenta_cod', 'cod');
    }
}
