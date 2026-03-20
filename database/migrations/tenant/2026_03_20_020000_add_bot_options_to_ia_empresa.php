<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->boolean('bot_puede_pedir')->default(true)->after('bot_medios_pago');
            $table->boolean('bot_puede_sugerir')->default(true)->after('bot_puede_pedir');
            $table->boolean('bot_puede_mas_vendidos')->default(false)->after('bot_puede_sugerir');
            $table->string('bot_atiende_nuevos', 10)->default('bot')->after('bot_puede_mas_vendidos');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn(['bot_puede_pedir', 'bot_puede_sugerir', 'bot_puede_mas_vendidos', 'bot_atiende_nuevos']);
        });
    }
};
