<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_ia', 100)->nullable()->comment('Nombre con el que se presenta el bot');
            $table->string('telefono_pedidos', 30)->nullable()->comment('Teléfono del local para recibir pedidos');
            $table->string('imagen_bienvenida', 255)->nullable()->comment('Ruta de la imagen que se envía al cliente nuevo');
            $table->text('bot_info')->nullable()->comment('Información del negocio (horarios, dirección, etc.)');
            $table->text('bot_instrucciones')->nullable()->comment('Instrucciones especiales para el bot');
            $table->json('bot_dias_reparto')->nullable();
            $table->boolean('bot_permite_retiro')->default(true);
            $table->boolean('bot_permite_envio')->default(true);
            $table->json('bot_medios_pago')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_empresa');
    }
};
