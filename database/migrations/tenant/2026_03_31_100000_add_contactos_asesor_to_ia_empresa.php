<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->string('contacto_asesor_1')->nullable()->after('telefono_pedidos');
            $table->string('contacto_asesor_2')->nullable()->after('contacto_asesor_1');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn(['contacto_asesor_1', 'contacto_asesor_2']);
        });
    }
};
