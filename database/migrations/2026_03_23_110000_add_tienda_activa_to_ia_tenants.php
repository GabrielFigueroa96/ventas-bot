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
            $table->boolean('tienda_activa')->default(false)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn('tienda_activa');
        });
    }
};
