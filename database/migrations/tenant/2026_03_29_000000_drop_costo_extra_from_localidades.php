<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('ia_localidades', function (Blueprint $table) {
            $table->dropColumn('costo_extra');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ia_localidades', function (Blueprint $table) {
            $table->decimal('costo_extra', 10, 2)->default(0)->after('provincia');
        });
    }
};
