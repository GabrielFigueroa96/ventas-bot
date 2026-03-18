<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->text('bot_info')->nullable()->after('prov')
                ->comment('Información del negocio que recibe el bot (horarios, dirección, etc.)');
            $table->text('bot_instrucciones')->nullable()->after('bot_info')
                ->comment('Instrucciones especiales sobre productos o reglas del negocio');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['bot_info', 'bot_instrucciones']);
        });
    }
};
