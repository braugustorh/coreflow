<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            // Atributos Planeados a 3 decimales
            $table->decimal('planned_easting', 10, 3)->nullable()->change();
            $table->decimal('planned_northing', 10, 3)->nullable()->change();
            $table->decimal('planned_elevation', 10, 3)->nullable()->change();
            $table->decimal('planned_dip', 6, 3)->nullable()->change();
            $table->decimal('planned_azimuth', 6, 3)->nullable()->change();

            // Atributos Instalados a 3 decimales
            $table->decimal('easting', 10, 3)->nullable()->change();
            $table->decimal('northing', 10, 3)->nullable()->change();
            $table->decimal('elevation', 10, 3)->nullable()->change();
            $table->decimal('dip', 6, 3)->nullable()->change();
            $table->decimal('azimuth', 6, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->decimal('planned_easting', 10, 3)->nullable()->change();
            $table->decimal('planned_northing', 10, 3)->nullable()->change();
            $table->decimal('planned_elevation', 8, 2)->nullable()->change();
            $table->decimal('planned_dip', 5, 2)->nullable()->change();
            $table->decimal('planned_azimuth', 5, 2)->nullable()->change();

            $table->decimal('easting', 10, 3)->nullable()->change();
            $table->decimal('northing', 10, 3)->nullable()->change();
            $table->decimal('elevation', 8, 2)->nullable()->change();
            $table->decimal('dip', 5, 2)->nullable()->change();
            $table->decimal('azimuth', 5, 2)->nullable()->change();
        });
    }
};
