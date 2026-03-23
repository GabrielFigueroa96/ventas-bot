<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->text('instagram_token')->nullable()->after('instagram_account_id')
                ->comment('Page Access Token con instagram_manage_messages para Instagram Direct');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('ia_tenants', function (Blueprint $table) {
            $table->dropColumn('instagram_token');
        });
    }
};
