<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->boolean('notif_negocio_enabled')->default(true)->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn('notif_negocio_enabled');
        });
    }
};
