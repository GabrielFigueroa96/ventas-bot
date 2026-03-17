<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seguimiento extends Model
{
    protected $table      = 'seguimientos';
    public    $timestamps = false;

    protected $fillable = ['cliente_id', 'tipo', 'mensaje_enviado', 'respondio', 'enviado_at'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
