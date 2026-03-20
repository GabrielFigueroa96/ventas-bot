<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ia_productos')) return;

        Schema::create('ia_productos', function (Blueprint $table) {
            $table->id();
            $table->decimal('cod', 6)->unique(); // igual que tablaplu.cod
            $table->decimal('precio', 10, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->boolean('disponible')->default(true);
            $table->text('notas_ia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_productos');
    }
};
