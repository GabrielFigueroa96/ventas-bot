<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_token_usos', function (Blueprint $table) {
            $table->decimal('costo_usd', 10, 8)->default(0)->after('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ia_token_usos', function (Blueprint $table) {
            $table->dropColumn('costo_usd');
        });
    }
};
