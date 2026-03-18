<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidad extends Model
{
    protected $table    = 'localidades';
    protected $fillable = ['nombre', 'dias_reparto', 'costo_extra', 'activo'];

    protected $casts = [
        'dias_reparto' => 'array',
        'costo_extra'  => 'float',
        'activo'       => 'boolean',
    ];

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function diasTexto(): string
    {
        if (empty($this->dias_reparto)) return 'Usa días globales';
        $labels = Empresa::DIAS_LABEL;
        return implode(', ', array_map(fn($d) => $labels[$d] ?? $d, $this->dias_reparto));
    }
}
