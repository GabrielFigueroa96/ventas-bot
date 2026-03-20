<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_carritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('ia_clientes')->cascadeOnDelete();
            $table->json('items');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_carritos');
    }
};
