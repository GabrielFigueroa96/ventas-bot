<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property float  $cod
 * @property float  $precio
 * @property string $descripcion
 * @property string $imagen
 * @property bool   $disponible
 * @property string $notas_ia
 */
class IaProducto extends Model
{
    protected $table = 'ia_productos';

    protected $fillable = [
        'cod',
        'precio',
        'descripcion',
        'imagen',
        'disponible',
        'notas_ia',
    ];

    protected $casts = [
        'cod'        => 'float',
        'precio'     => 'float',
        'disponible' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'cod', 'cod');
    }

    public function localidades()
    {
        return $this->hasMany(ProductoLocalidad::class, 'cod', 'cod');
    }
}
