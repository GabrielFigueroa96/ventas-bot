<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $phone
 * @property string $name
 * @property string $estado
 * @property string $last_order_at
 */
class Cliente extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'name',
        'estado',
        'last_order_at',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'cliente_id');
    }
}
