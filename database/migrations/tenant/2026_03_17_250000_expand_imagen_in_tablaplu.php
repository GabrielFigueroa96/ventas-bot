<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tablaplu', function (Blueprint $table) {
            $table->string('imagen', 255)->default('sinimagen.webp')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tablaplu', function (Blueprint $table) {
            $table->string('imagen', 40)->default('sinimagen.webp')->change();
        });
    }
};
