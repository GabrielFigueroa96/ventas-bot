<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $phone
 * @property string $name
 * @property string $estado
 * @property string $modo
 * @property string $last_order_at
 * @property string|null $cuenta_cod
 * @property-read Cuenta|null $cuenta
 */
class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone', 'name',
        'localidad', 'provincia', 'localidad_id',
        'calle', 'numero', 'dato_extra',
        'estado', 'modo', 'last_order_at', 'cuenta_cod',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'cliente_id');
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_cod', 'cod');
    }

    public function localidadObj()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function seguimientos()
    {
        return $this->hasMany(Seguimiento::class);
    }
}
