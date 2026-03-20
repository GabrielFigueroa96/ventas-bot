<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('nro')->unique();           // vincula con pedidos.nro
            $table->string('codcli', 50);                      // código de cuenta
            $table->foreignId('idcliente')->constrained('ia_clientes')->cascadeOnDelete();
            $table->string('nomcli', 100);
            $table->date('fecha');                             // fecha de entrega
            $table->enum('tipo_entrega', ['envio', 'retiro']);
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'cuenta_corriente', 'otro']);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('obs')->nullable();
            $table->smallInteger('estado')->default(0);        // 0 pendiente, 1 finalizado
            $table->timestamp('pedido_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_pedidos');
    }
};
