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
        if (!Schema::hasTable('tablaplu')) {
            Schema::create('tablaplu', function (Blueprint $table) {
                $table->id();
                $table->decimal('cod', 6);
                $table->string('des');
                $table->decimal('iva', 4, 2);
                $table->integer('grupo');
                $table->string('desgrupo', 70);
                $table->integer('unid');
                $table->decimal('kilos', 15, 3);
                $table->decimal('pre', 10, 2);
                $table->decimal('cant', 12, 3);
                $table->decimal('pre2', 10, 2);
                $table->decimal('pre3', 10, 2);
                $table->string('tipo', 10);
                $table->string('imagen', 40)->default('sinimagen.webp');
                $table->string('descripcion', 30)->default('');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablaplu');
    }
};
