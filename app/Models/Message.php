<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cliente;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'message',
        'direction',
        'type',
        'wamid',
        'media_path',
    ];

    // Fecha formateada con timezone de la app
    public function getFechaAttribute(): string
    {
        return $this->created_at
            ->setTimezone(config('app.timezone'))
            ->format('d/m H:i');
    }

    public function client()
    {
        return $this->belongsTo(Cliente::class);
    }
}
