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
        'type'
    ];

    public function client()
    {
        return $this->belongsTo(Cliente::class);
    }
}
