<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->string('notif_template_nombre')->nullable()->after('notif_negocio_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn('notif_template_nombre');
        });
    }
};
