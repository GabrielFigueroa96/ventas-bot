<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->decimal('pedido_minimo', 10, 2)->default(0)->after('slug');
            $table->string('imagen_tienda')->nullable()->after('pedido_minimo');
            $table->boolean('tienda_ocultar_precios')->default(false)->after('imagen_tienda');
            $table->string('tienda_facebook')->nullable()->after('tienda_ocultar_precios');
            $table->string('tienda_instagram')->nullable()->after('tienda_facebook');
            $table->string('tienda_tiktok')->nullable()->after('tienda_instagram');
        });
    }

    public function down(): void
    {
        Schema::table('ia_empresa', function (Blueprint $table) {
            $table->dropColumn([
                'pedido_minimo', 'imagen_tienda', 'tienda_ocultar_precios',
                'tienda_facebook', 'tienda_instagram', 'tienda_tiktok',
            ]);
        });
    }
};
