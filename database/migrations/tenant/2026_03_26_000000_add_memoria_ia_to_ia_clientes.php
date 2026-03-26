<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_clientes', function (Blueprint $table) {
            $table->text('memoria_ia')->nullable()->after('dato_extra');
        });
    }

    public function down(): void
    {
        Schema::table('ia_clientes', function (Blueprint $table) {
            $table->dropColumn('memoria_ia');
        });
    }
};
