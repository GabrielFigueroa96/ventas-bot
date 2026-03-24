<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_recordatorios', function (Blueprint $table) {
            $table->string('template_nombre')->nullable()->after('imagen_url');
        });
    }

    public function down(): void
    {
        Schema::table('ia_recordatorios', function (Blueprint $table) {
            $table->dropColumn('template_nombre');
        });
    }
};
