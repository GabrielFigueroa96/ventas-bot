<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->boolean('rec_apertura')->default(false)->after('activo');
            $table->text('rec_apertura_mensaje')->nullable()->after('rec_apertura');
            $table->string('rec_apertura_template', 200)->nullable()->after('rec_apertura_mensaje');
            $table->boolean('rec_cierre')->default(false)->after('rec_apertura_template');
            $table->unsignedTinyInteger('rec_cierre_horas')->nullable()->after('rec_cierre');
            $table->text('rec_cierre_mensaje')->nullable()->after('rec_cierre_horas');
            $table->string('rec_cierre_template', 200)->nullable()->after('rec_cierre_mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('ia_localidades', function (Blueprint $table) {
            $table->dropColumn([
                'rec_apertura', 'rec_apertura_mensaje', 'rec_apertura_template',
                'rec_cierre', 'rec_cierre_horas', 'rec_cierre_mensaje', 'rec_cierre_template',
            ]);
        });
    }
};
