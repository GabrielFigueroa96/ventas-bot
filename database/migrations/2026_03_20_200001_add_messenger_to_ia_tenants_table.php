<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->string('canal', 20)->default('whatsapp')->after('nombre');
            $table->string('page_id')->nullable()->unique()->after('canal');
            $table->text('messenger_token')->nullable()->after('page_id');
            $table->string('phone_number_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn(['canal', 'page_id', 'messenger_token']);
            $table->string('phone_number_id')->nullable(false)->change();
        });
    }
};
