<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablaplu', function (Blueprint $table) {
            $table->string('descripcion', 255)->default('')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tablaplu', function (Blueprint $table) {
            $table->string('descripcion', 30)->default('')->change();
        });
    }
};
