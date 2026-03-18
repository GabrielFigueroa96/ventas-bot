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
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('mensaje');
            $table->enum('tipo', ['libre', 'recomendacion', 'repetir_pedido'])->default('libre');
            $table->string('filtro_localidad', 100)->nullable();
            $table->string('filtro_provincia', 100)->nullable();
            $table->json('dias')->nullable()->comment('Array [0=dom,1=lun,…,6=sab]. Null = todos los días.');
            $table->time('hora')->default('09:00');
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
