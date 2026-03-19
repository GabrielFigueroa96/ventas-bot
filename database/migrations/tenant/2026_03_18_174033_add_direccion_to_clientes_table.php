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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('calle', 100)->nullable()->after('localidad_id');
            $table->string('numero', 20)->nullable()->after('calle');
            $table->string('dato_extra', 150)->nullable()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['calle', 'numero', 'dato_extra']);
        });
    }
};
