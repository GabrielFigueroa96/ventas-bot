<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return 'mysql';
    }

    public function up(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
