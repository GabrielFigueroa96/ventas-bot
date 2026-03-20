<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tablaplu_id')->unique();
            $table->foreign('tablaplu_id')->references('id')->on('tablaplu')->cascadeOnDelete();
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
