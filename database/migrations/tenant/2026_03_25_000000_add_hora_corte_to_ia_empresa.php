<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            // Hora límite para pedidos del día (ej: "10:00").
            // Pedidos antes de esta hora se asignan a hoy; después, al próximo día de reparto.
            $table->string('bot_hora_corte', 5)->nullable()->after('bot_fechas_cerrado');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn('bot_hora_corte');
        });
    }
};
