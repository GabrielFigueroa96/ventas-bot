<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $table      = 'tablaplu';
    protected $primaryKey = 'cod';
    public    $incrementing = false;
    protected $keyType    = 'float';
    public    $timestamps = false;

    protected $fillable = [
        'des',
        'pre',
        'tipo',
        'grupo',
        'desgrupo',
        'imagen',
        'descripcion',
        'notas_ia',
    ];

    protected static function boot()
    {
        parent::boot();
        // Cascade: si se borra el producto de tablaplu, se borra su registro en ia_productos
        static::deleting(function (self $producto) {
            $producto->iaProducto?->delete();
        });
    }

    public function iaProducto()
    {
        return $this->hasOne(IaProducto::class, 'cod', 'cod');
    }

    /**
     * Productos habilitados para el bot (registrados en ia_productos y disponibles).
     * Selecciona precio, descripcion, imagen y notas_ia desde ia_productos.
     */
    public function scopeParaBot($query)
    {
        return $query
            ->join('ia_productos', 'tablaplu.cod', '=', 'ia_productos.cod')
            ->where('ia_productos.disponible', true)
            ->select(
                'tablaplu.cod',
                'tablaplu.des',
                'tablaplu.tipo',
                'tablaplu.grupo',
                'tablaplu.desgrupo',
                'ia_productos.precio',
                'ia_productos.descripcion',
                'ia_productos.imagen',
                'ia_productos.notas_ia',
            );
    }
}
