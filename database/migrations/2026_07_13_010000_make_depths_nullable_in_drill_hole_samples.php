<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hace nullable from_depth y to_depth para soportar muestras de control
     * que por definición no tienen intervalos de profundidad.
     */
    public function up(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('from_depth', 8, 2)->nullable()->change();
            $table->decimal('to_depth', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('from_depth', 8, 2)->nullable(false)->change();
            $table->decimal('to_depth', 8, 2)->nullable(false)->change();
        });
    }
};
