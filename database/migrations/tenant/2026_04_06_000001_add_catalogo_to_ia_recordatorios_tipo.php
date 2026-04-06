<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ia_recordatorios MODIFY tipo ENUM('libre', 'catalogo') NOT NULL DEFAULT 'libre'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ia_recordatorios MODIFY tipo ENUM('libre', 'recomendacion', 'repetir_pedido') NOT NULL DEFAULT 'libre'");
    }
};
