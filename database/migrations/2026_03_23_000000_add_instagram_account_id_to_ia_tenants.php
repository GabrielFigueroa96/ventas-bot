<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->string('instagram_account_id')->nullable()->unique()->after('messenger_page_id')
                ->comment('Instagram Business Account ID (entry[0].id del webhook de Instagram)');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn('instagram_account_id');
        });
    }
};
