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
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->string('provincia', 100)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->dropColumn('provincia');
        });
    }
};
