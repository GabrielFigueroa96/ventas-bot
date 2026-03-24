<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            // Nuevo: horarios por día con múltiples turnos
            // Formato: {"1":[{"de":"08:00","a":"12:00"},{"de":"17:00","a":"21:00"}], ...}
            $table->json('bot_horarios')->nullable()->after('bot_dias_reparto');
        });

        Schema::table('ia_empresa', function (Blueprint $table) {
            $drop = [];
            foreach (['bot_dias_abierto', 'bot_horario_apertura', 'bot_horario_cierre'] as $col) {
                if (Schema::hasColumn('ia_empresa', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn('bot_horarios');
            $table->json('bot_dias_abierto')->nullable();
            $table->string('bot_horario_apertura', 5)->nullable();
            $table->string('bot_horario_cierre', 5)->nullable();
        });
    }
};
