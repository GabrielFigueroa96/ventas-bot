<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IaProducto extends Model
{
    protected $table = 'ia_productos';

    protected $fillable = [
        'tablaplu_id',
        'precio',
        'descripcion',
        'imagen',
        'disponible',
        'notas_ia',
    ];

    protected $casts = [
        'precio'     => 'float',
        'disponible' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'tablaplu_id', 'id');
    }
}
