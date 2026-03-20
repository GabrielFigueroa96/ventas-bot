<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ia_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('phone_number_id')->unique()->comment('ID del número en Meta/WhatsApp');
            $table->string('webhook_token')->unique();
            $table->text('whatsapp_api_key');
            $table->text('openai_api_key');
            $table->string('db_host');
            $table->string('db_name');
            $table->string('db_user');
            $table->string('db_password');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ia_tenants');
    }
};
