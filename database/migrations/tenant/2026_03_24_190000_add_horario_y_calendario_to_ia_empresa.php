<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->json('bot_dias_abierto')->nullable()->after('bot_dias_reparto');    // días en que el local está abierto (para retiro)
            $table->string('bot_horario_apertura', 5)->nullable()->after('bot_dias_abierto'); // "09:00"
            $table->string('bot_horario_cierre', 5)->nullable()->after('bot_horario_apertura'); // "18:00"
            $table->json('bot_fechas_cerrado')->nullable()->after('bot_horario_cierre'); // ["2026-12-25","2027-01-01"]
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn(['bot_dias_abierto', 'bot_horario_apertura', 'bot_horario_cierre', 'bot_fechas_cerrado']);
        });
    }
};
