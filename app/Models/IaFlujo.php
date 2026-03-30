<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IaFlujo extends Model
{
    protected $table    = 'ia_flujos';
    protected $fillable = ['nombre', 'definicion', 'activo'];
    protected $casts    = ['definicion' => 'array', 'activo' => 'boolean'];
}
