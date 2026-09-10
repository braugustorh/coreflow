<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade campos espaciales, técnicos y de categorización a la tabla drill_holes.
     */
    public function up(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            // ─── Relaciones ─────────────────────────────────────────────
            $table->foreignId('sede_id')
                ->nullable()
                ->after('id')
                ->constrained('sedes')
                ->onDelete('restrict');

            $table->foreignId('proyecto_id')
                ->nullable()
                ->after('sede_id')
                ->constrained('proyectos')
                ->onDelete('restrict');

            // ─── Datos Espaciales ────────────────────────────────────────
            $table->decimal('easting', 10, 3)->nullable()->after('nombre_barreno');
            $table->decimal('northing', 10, 3)->nullable()->after('easting');
            $table->decimal('elevation', 8, 2)->nullable()->after('northing');
            $table->decimal('dip', 5, 2)->nullable()->after('elevation');
            $table->decimal('azimuth', 5, 2)->nullable()->after('dip');

            // ─── Especificaciones Técnicas ───────────────────────────────
            $table->decimal('max_depth', 8, 2)->nullable()->after('azimuth');
            $table->string('drilling_type')->nullable()->after('max_depth');
            $table->string('core_size')->nullable()->after('drilling_type');
            $table->string('purpose')->nullable()->after('core_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            // Eliminar claves foráneas primero
            $table->dropForeign(['sede_id']);
            $table->dropForeign(['proyecto_id']);

            $table->dropColumn([
                'sede_id',
                'proyecto_id',
                'easting',
                'northing',
                'elevation',
                'dip',
                'azimuth',
                'max_depth',
                'drilling_type',
                'core_size',
                'purpose',
            ]);
        });
    }
};
