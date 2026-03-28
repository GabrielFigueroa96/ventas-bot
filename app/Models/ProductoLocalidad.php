<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoLocalidad extends Model
{
    protected $table    = 'ia_producto_localidad';
    protected $fillable = ['cod', 'localidad_id', 'precio', 'dias_reparto'];

    protected $casts = [
        'cod'          => 'float',
        'precio'       => 'float',
        'dias_reparto' => 'array',
    ];

    public function localidad()
    {
        return $this->belongsTo(Localidad::class);
    }

    public function producto()
    {
        return $this->belongsTo(IaProducto::class, 'cod', 'cod');
    }
}
