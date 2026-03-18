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
    ];


}
