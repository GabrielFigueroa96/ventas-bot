<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_pedidos', function (Blueprint $table) {
            $table->unsignedInteger('vmayo_nro')->nullable()->after('estado')
                ->comment('nro del registro vmayo vinculado al pasar a Preparado');
        });
    }

    public function down(): void
    {
        Schema::table('ia_pedidos', function (Blueprint $table) {
            $table->dropColumn('vmayo_nro');
        });
    }
};
