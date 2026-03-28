<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localidad extends Model
{
    protected $table    = 'ia_localidades';
    protected $fillable = ['nombre', 'provincia', 'dias_reparto', 'costo_extra', 'activo'];

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
        $labels = \App\Models\IaEmpresa::DIAS_LABEL;
        return implode(', ', collect($this->dias_reparto)->map(function ($d) use ($labels) {
            $dia = is_array($d) ? $d['dia'] : (int) $d;
            return $labels[$dia] ?? $dia;
        })->toArray());
    }

    /** Devuelve el config normalizado: siempre array de arrays con clave 'dia'. */
    public function diasConfig(): array
    {
        return collect($this->dias_reparto ?? [])->map(function ($d) {
            return is_array($d) ? $d : ['dia' => (int) $d];
        })->toArray();
    }
}
