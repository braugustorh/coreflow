<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cambia control_type de ENUM a VARCHAR(50) para soportar
     * valores dinámicos como BLANK-ML, BLANK-SN, Standard, Duplicate, etc.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE drill_hole_samples MODIFY COLUMN control_type VARCHAR(50) NULL');
        }

        // 2. Luego normalizar los valores existentes al nuevo vocabulario en inglés
        DB::table('drill_hole_samples')->where('control_type', 'Blanco')->update(['control_type' => 'BLANK']);
        DB::table('drill_hole_samples')->where('control_type', 'Estándar')->update(['control_type' => 'Standard']);
        DB::table('drill_hole_samples')->where('control_type', 'Duplicado')->update(['control_type' => 'Duplicate']);
    }

    public function down(): void
    {
        // Revertir: primero normalizar de vuelta, luego cambiar a ENUM
        DB::table('drill_hole_samples')
            ->where('control_type', 'BLANK')
            ->orWhere('control_type', 'like', 'BLANK-%')
            ->update(['control_type' => 'Blanco']);

        DB::table('drill_hole_samples')
            ->where('control_type', 'Standard')
            ->update(['control_type' => 'Estándar']);

        DB::table('drill_hole_samples')
            ->where('control_type', 'Duplicate')
            ->update(['control_type' => 'Duplicado']);

        DB::statement("ALTER TABLE drill_hole_samples MODIFY COLUMN control_type ENUM('Estándar', 'Blanco', 'Duplicado') NULL");
    }
};
