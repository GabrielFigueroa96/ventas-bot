<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_producto_localidad', function (Blueprint $table) {
            $table->id();
            $table->decimal('cod', 10, 2);
            $table->unsignedBigInteger('localidad_id');
            $table->decimal('precio', 10, 2)->nullable()->comment('Null = usa el precio base del producto');
            $table->json('dias_reparto')->nullable()->comment('Null = usa los días de la localidad');
            $table->timestamps();

            $table->unique(['cod', 'localidad_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_producto_localidad');
    }
};
