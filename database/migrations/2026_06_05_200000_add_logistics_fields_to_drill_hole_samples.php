<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migración ADITIVA: no elimina columnas existentes para preservar datos.
     */
    public function up(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            // 1. Renombrar responsable_supervisor → responsible_supervisor
            //    Solo si la columna antigua existe y la nueva no
            if (Schema::hasColumn('drill_hole_samples', 'responsable_supervisor')
                && !Schema::hasColumn('drill_hole_samples', 'responsible_supervisor')) {
                $table->renameColumn('responsable_supervisor', 'responsible_supervisor');
            }

            // 2. Agregar bags_sacks_status si no existe
            if (!Schema::hasColumn('drill_hole_samples', 'bags_sacks_status')) {
                $table->enum('bags_sacks_status', ['Printed', 'In Process', 'Completed'])
                    ->nullable()
                    ->after('responsible_supervisor');
            }

            // 3. Agregar is_archived si no existe
            if (!Schema::hasColumn('drill_hole_samples', 'is_archived')) {
                $table->boolean('is_archived')
                    ->default(false)
                    ->after('bags_sacks_status');
            }
        });

        // 4. Normalizar valores de sampling_status al nuevo enum
        //    El campo existente era varchar, lo dejamos como varchar pero normalizamos valores
        DB::table('drill_hole_samples')
            ->where('sampling_status', 'PENDING')
            ->update(['sampling_status' => 'Pending']);

        DB::table('drill_hole_samples')
            ->where('sampling_status', 'IN_PROGRESS')
            ->update(['sampling_status' => 'In Progress']);

        DB::table('drill_hole_samples')
            ->where('sampling_status', 'COMPLETED')
            ->update(['sampling_status' => 'Completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            // Revertir renombramiento
            if (Schema::hasColumn('drill_hole_samples', 'responsible_supervisor')
                && !Schema::hasColumn('drill_hole_samples', 'responsable_supervisor')) {
                $table->renameColumn('responsible_supervisor', 'responsable_supervisor');
            }

            // Eliminar columnas agregadas
            if (Schema::hasColumn('drill_hole_samples', 'bags_sacks_status')) {
                $table->dropColumn('bags_sacks_status');
            }

            if (Schema::hasColumn('drill_hole_samples', 'is_archived')) {
                $table->dropColumn('is_archived');
            }
        });
    }
};
