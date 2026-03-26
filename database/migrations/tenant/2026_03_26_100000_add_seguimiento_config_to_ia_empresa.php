<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->boolean('seguimiento_carrito_activo')->default(true)->after('bot_notifica_estados');
            $table->unsignedTinyInteger('seguimiento_carrito_horas')->default(2)->after('seguimiento_carrito_activo');
            $table->boolean('seguimiento_sin_pedido_activo')->default(true)->after('seguimiento_carrito_horas');
            $table->unsignedTinyInteger('seguimiento_sin_pedido_dias')->default(3)->after('seguimiento_sin_pedido_activo');
            $table->boolean('seguimiento_inactivo_activo')->default(true)->after('seguimiento_sin_pedido_dias');
            $table->unsignedTinyInteger('seguimiento_inactivo_dias')->default(7)->after('seguimiento_inactivo_activo');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn([
                'seguimiento_carrito_activo', 'seguimiento_carrito_horas',
                'seguimiento_sin_pedido_activo', 'seguimiento_sin_pedido_dias',
                'seguimiento_inactivo_activo', 'seguimiento_inactivo_dias',
            ]);
        });
    }
};
