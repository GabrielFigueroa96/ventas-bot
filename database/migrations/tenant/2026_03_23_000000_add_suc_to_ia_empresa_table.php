<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->string('suc', 10)->nullable()->after('bot_atiende_nuevos')
                ->comment('Código de sucursal que se asigna a los pedidos del bot');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn('suc');
        });
    }
};
