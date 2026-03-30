<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void {
        Schema::connection('tenant')->create('ia_flujos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->json('definicion')->nullable();
            $table->boolean('activo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::connection('tenant')->dropIfExists('ia_flujos');
    }
};
