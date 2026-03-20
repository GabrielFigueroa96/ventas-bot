<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $connection = 'mysql';
    protected $table = 'ia_message_logs';

    protected $fillable = [
        'tenant_id',
        'phone',
        'type',
        'message',
        'reply',
        'tokens_input',
        'tokens_output',
        'enviado',
    ];

    protected $casts = [
        'enviado' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
