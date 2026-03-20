<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pedidos')) return;

        Schema::create('pedidos', function (Blueprint $table) {
            $table->increments('reg');
            $table->unsignedInteger('nro')->default(0)->index();
            $table->string('fecha', 20)->nullable();
            $table->string('nomcli', 100)->nullable();
            $table->integer('cant')->default(0);
            $table->string('descrip', 200)->nullable();
            $table->decimal('kilos', 10, 3)->default(0);
            $table->string('codigo', 50)->nullable();
            $table->integer('codcli')->default(0)->index();
            $table->tinyInteger('estado')->default(0);
            $table->string('fecfin', 20)->nullable();
            $table->text('obs')->nullable();
            $table->string('suc', 10)->nullable();
            $table->string('pv', 10)->nullable();
            $table->unsignedInteger('venta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
