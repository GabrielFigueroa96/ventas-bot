<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->json('bot_dias_reparto')->nullable()->after('bot_instrucciones')
                ->comment('[0=dom,1=lun,...,6=sab]. Null = todos los días.');
            $table->boolean('bot_permite_retiro')->default(true)->after('bot_dias_reparto');
            $table->boolean('bot_permite_envio')->default(true)->after('bot_permite_retiro');
            $table->json('bot_medios_pago')->nullable()->after('bot_permite_envio')
                ->comment('Array de medios habilitados: efectivo, transferencia, cuenta_corriente, otro');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['bot_dias_reparto','bot_permite_retiro','bot_permite_envio','bot_medios_pago']);
        });
    }
};
