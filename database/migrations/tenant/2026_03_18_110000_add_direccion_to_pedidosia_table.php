<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_pedidosia', function (Blueprint $table) {
            $table->string('calle', 100)->nullable()->after('tipo_entrega');
            $table->string('numero', 20)->nullable()->after('calle');
            $table->string('localidad', 100)->nullable()->after('numero');
            $table->string('dato_extra', 150)->nullable()->after('localidad');
        });
    }

    public function down(): void
    {
        Schema::table('ia_pedidosia', function (Blueprint $table) {
            $table->dropColumn(['calle', 'numero', 'localidad', 'dato_extra']);
        });
    }
};
