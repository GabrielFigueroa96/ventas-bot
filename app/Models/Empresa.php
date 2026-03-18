<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $nombre
 * @property string $domicilio
 * @property string $prov
 */
class Empresa extends Model
{
    protected $table = 'empresa';
    public $timestamps = false;

    protected $fillable = ['bot_info', 'bot_instrucciones'];
}
