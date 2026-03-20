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
        Schema::table('ia_clientes', function (Blueprint $table) {
            $table->foreignId('localidad_id')->nullable()->after('provincia')
                ->constrained('ia_localidades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ia_clientes', function (Blueprint $table) {
            $table->dropForeign(['localidad_id']);
            $table->dropColumn('localidad_id');
        });
    }
};
