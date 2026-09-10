<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade campos de atributos planeados y metadatos del reporte de levantamiento a drill_holes.
     */
    public function up(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            // ─── Atributos Planeados ───────────────────────────────────────
            $table->decimal('planned_easting', 10, 3)->nullable()->after('nombre_barreno');
            $table->decimal('planned_northing', 10, 3)->nullable()->after('planned_easting');
            $table->decimal('planned_elevation', 8, 2)->nullable()->after('planned_northing');
            $table->decimal('planned_dip', 5, 2)->nullable()->after('planned_elevation');
            $table->decimal('planned_azimuth', 5, 2)->nullable()->after('planned_dip');

            // ─── Reporte e Informe de Levantamiento (Atributos Instalados) ──
            $table->string('survey_pdf_path')->nullable()->after('azimuth');
            $table->string('planilla')->nullable()->after('survey_pdf_path');
            $table->string('survey_responsible')->nullable()->after('planilla');
            $table->date('survey_date')->nullable()->after('survey_responsible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->dropColumn([
                'planned_easting',
                'planned_northing',
                'planned_elevation',
                'planned_dip',
                'planned_azimuth',
                'survey_pdf_path',
                'planilla',
                'survey_responsible',
                'survey_date',
            ]);
        });
    }
};
