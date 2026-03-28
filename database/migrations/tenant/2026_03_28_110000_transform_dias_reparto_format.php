<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $localidades = DB::table('ia_localidades')->get(['id', 'dias_reparto']);
        foreach ($localidades as $loc) {
            $dias = json_decode($loc->dias_reparto, true);
            if (empty($dias) || !is_array($dias)) continue;
            if (is_array($dias[0])) continue; // Ya en nuevo formato
            $nuevo = array_map(fn($d) => ['dia' => (int) $d], $dias);
            DB::table('ia_localidades')->where('id', $loc->id)->update([
                'dias_reparto' => json_encode($nuevo),
            ]);
        }
    }

    public function down(): void
    {
        $localidades = DB::table('ia_localidades')->get(['id', 'dias_reparto']);
        foreach ($localidades as $loc) {
            $dias = json_decode($loc->dias_reparto, true);
            if (empty($dias) || !is_array($dias)) continue;
            if (!is_array($dias[0])) continue; // Ya en formato viejo
            $viejo = array_column($dias, 'dia');
            DB::table('ia_localidades')->where('id', $loc->id)->update([
                'dias_reparto' => json_encode($viejo),
            ]);
        }
    }
};
