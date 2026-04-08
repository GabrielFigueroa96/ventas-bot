<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('ia_recordatorios', function (Blueprint $table) {
            $table->json('productos_flash')->nullable()
                ->comment('Lista de productos para pedido express [{cod,des,precio,tipo}]. Null = no express.')
                ->after('template_nombre');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ia_recordatorios', function (Blueprint $table) {
            $table->dropColumn('productos_flash');
        });
    }
};
