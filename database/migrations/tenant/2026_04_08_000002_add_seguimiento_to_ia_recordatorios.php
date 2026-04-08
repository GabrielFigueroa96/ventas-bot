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
            $table->unsignedTinyInteger('seguimiento_horas_antes')->nullable()
                ->comment('Horas antes del cierre del flash para enviar seguimiento a quien no pidió.')
                ->after('flash_horas');
            $table->text('seguimiento_mensaje')->nullable()
                ->comment('Mensaje del seguimiento. Soporta {nombre}.')
                ->after('seguimiento_horas_antes');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ia_recordatorios', function (Blueprint $table) {
            $table->dropColumn(['seguimiento_horas_antes', 'seguimiento_mensaje']);
        });
    }
};
