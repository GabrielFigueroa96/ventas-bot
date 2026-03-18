<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrito extends Model
{
    protected $fillable = [
        'cliente_id',
        'items',
        'expires_at',
    ];

    protected $casts = [
        'items'      => 'array',
        'expires_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function expirado(): bool
    {
        return $this->expires_at->isPast();
    }
}
