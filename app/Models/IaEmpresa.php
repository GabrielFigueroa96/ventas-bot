<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IaEmpresa extends Model
{
    protected $table = 'ia_empresa';

    protected $fillable = [
        'nombre_ia',
        'telefono_pedidos',
        'imagen_bienvenida',
        'imagen_tienda',
        'bot_info',
        'bot_instrucciones',
        'bot_dias_reparto',
        'bot_permite_retiro',
        'bot_permite_envio',
        'bot_medios_pago',
        'bot_puede_pedir',
        'bot_puede_sugerir',
        'bot_puede_mas_vendidos',
        'bot_atiende_nuevos',
        'bot_notifica_estados',
        'seguimiento_carrito_activo',
        'seguimiento_carrito_horas',
        'seguimiento_sin_pedido_activo',
        'seguimiento_sin_pedido_dias',
        'seguimiento_inactivo_activo',
        'seguimiento_inactivo_dias',
        'suc',
        'pv',
        'slug',
        'pedido_minimo',
        'tienda_ocultar_precios',
        'tienda_facebook',
        'tienda_instagram',
        'tienda_tiktok',
        'two_factor_enabled',
        'notif_negocio_enabled',
        'notif_template_nombre',
        'bot_horarios',
        'bot_fechas_cerrado',
        'bot_hora_corte',
        'max_pedidos_pendientes',
    ];

    protected $casts = [
        'bot_dias_reparto'       => 'array',
        'bot_horarios'           => 'array',
        'bot_fechas_cerrado'     => 'array',
        'bot_permite_retiro'     => 'boolean',
        'bot_permite_envio'      => 'boolean',
        'bot_medios_pago'        => 'array',
        'bot_puede_pedir'        => 'boolean',
        'bot_puede_sugerir'      => 'boolean',
        'bot_puede_mas_vendidos' => 'boolean',
        'bot_notifica_estados'          => 'boolean',
        'seguimiento_carrito_activo'    => 'boolean',
        'seguimiento_carrito_horas'     => 'integer',
        'seguimiento_sin_pedido_activo' => 'boolean',
        'seguimiento_sin_pedido_dias'   => 'integer',
        'seguimiento_inactivo_activo'   => 'boolean',
        'seguimiento_inactivo_dias'     => 'integer',
        'tienda_ocultar_precios'        => 'boolean',
        'two_factor_enabled'     => 'boolean',
        'notif_negocio_enabled'  => 'boolean',
        'pedido_minimo'          => 'float',
    ];

    public const DIAS_LABEL = [
        0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes',
        3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado',
    ];

    public const MEDIOS_PAGO = [
        'efectivo'         => 'Efectivo',
        'transferencia'    => 'Transferencia',
        'cuenta_corriente' => 'Cuenta corriente',
        'otro'             => 'Otro',
    ];
}
