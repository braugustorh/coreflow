<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->string('target')->nullable()->after('nombre_barreno');
            $table->decimal('start_depth', 8, 2)->nullable()->default(0.00)->after('max_depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->dropColumn(['target', 'start_depth']);
        });
    }
};
