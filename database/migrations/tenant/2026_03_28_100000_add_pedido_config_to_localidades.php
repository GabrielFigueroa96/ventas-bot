<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->time('hora_corte')->nullable()->after('dias_reparto');
            $table->unsignedTinyInteger('dias_anticipacion')->nullable()->after('hora_corte')
                  ->comment('Días antes del reparto en que se cierra el pedido. 0 = mismo día, 1 = día anterior, etc.');
        });
    }

    public function down(): void
    {
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->dropColumn(['hora_corte', 'dias_anticipacion']);
        });
    }
};
