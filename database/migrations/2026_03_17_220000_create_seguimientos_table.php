<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->enum('tipo', ['sin_pedido', 'inactivo']);
            $table->text('mensaje_enviado');
            $table->boolean('respondio')->default(false);
            $table->timestamp('enviado_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimientos');
    }
};
