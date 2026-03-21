<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            // Facebook Page ID para enviar mensajes (distinto del Instagram User ID en page_id)
            $table->string('messenger_page_id')->nullable()->after('messenger_token');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn('messenger_page_id');
        });
    }
};
