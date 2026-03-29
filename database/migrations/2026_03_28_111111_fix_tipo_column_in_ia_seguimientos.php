<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('ia_seguimientos', function (Blueprint $table) {
            $table->string('tipo', 50)->change();
            $table->text('mensaje_enviado')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ia_seguimientos', function (Blueprint $table) {
            $table->string('tipo', 20)->change();
            $table->string('mensaje_enviado')->nullable()->change();
        });
    }
};
