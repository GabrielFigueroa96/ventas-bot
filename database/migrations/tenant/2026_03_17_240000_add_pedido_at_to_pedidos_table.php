<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedidos', 'pedido_at')) return;

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dateTime('pedido_at')->nullable()->after('obs');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('pedido_at');
        });
    }
};
