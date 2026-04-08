<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recordatorio extends Model
{
    protected $table = 'ia_recordatorios';

    protected $fillable = [
        'nombre', 'mensaje', 'imagen_url', 'template_nombre', 'productos_flash',
        'flash_localidades', 'flash_horas', 'seguimiento_horas_antes', 'seguimiento_mensaje',
        'tipo', 'filtro_localidad', 'filtro_provincia',
        'dias', 'hora', 'activo', 'ultimo_envio_at',
    ];

    protected $casts = [
        'dias'              => 'array',
        'productos_flash'   => 'array',
        'flash_localidades' => 'array',
        'activo'            => 'boolean',
        'ultimo_envio_at'   => 'datetime',
    ];

    public static array $DIAS_LABEL = [
        0 => 'Dom', 1 => 'Lun', 2 => 'Mar',
        3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb',
    ];

    public function diasTexto(): string
    {
        if (empty($this->dias)) return 'Todos los días';
        return implode(', ', array_map(fn($d) => self::$DIAS_LABEL[$d] ?? $d, $this->dias));
    }

    // Determina si este recordatorio debe ejecutarse ahora mismo
    public function deberia(): bool
    {
        if (!$this->activo) return false;
        if ($this->ultimo_envio_at && $this->ultimo_envio_at->isToday()) return false;
        if (!empty($this->dias) && !in_array((int) now()->format('w'), $this->dias)) return false;

        // Dispara si está dentro de los 10 minutos posteriores a la hora configurada
        $horaConfig = \Carbon\Carbon::today()->setTimeFromTimeString($this->hora);
        $diff = now()->diffInMinutes($horaConfig, false);
        return $diff >= -10 && $diff <= 0;
    }
}
