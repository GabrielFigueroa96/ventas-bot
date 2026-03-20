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
        Schema::create('ia_localidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->json('dias_reparto')->nullable()->comment('[0=dom,1=lun,...,6=sab]. Null = usa los días globales.');
            $table->decimal('costo_extra', 8, 2)->default(0)->comment('Recargo por zona (0 por ahora)');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_localidades');
    }
};
