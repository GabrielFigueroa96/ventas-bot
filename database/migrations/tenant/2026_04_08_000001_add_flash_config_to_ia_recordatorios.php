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
            $table->json('flash_localidades')->nullable()
                ->comment('Localidades destino del pedido express (array de nombres). Anula filtro_localidad para envío y activación.')
                ->after('productos_flash');
            $table->unsignedSmallInteger('flash_horas')->nullable()->default(24)
                ->comment('Duración en horas del modo pedido express desde el envío.')
                ->after('flash_localidades');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ia_recordatorios', function (Blueprint $table) {
            $table->dropColumn(['flash_localidades', 'flash_horas']);
        });
    }
};
