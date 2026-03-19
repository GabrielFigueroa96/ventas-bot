<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('nro');
            $table->string('pv')->default('');
            $table->integer('estado_notificado');
            $table->string('phone');
            $table->timestamp('enviado_at')->useCurrent();

            $table->unique(['nro', 'pv', 'estado_notificado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_notificaciones');
    }
};
