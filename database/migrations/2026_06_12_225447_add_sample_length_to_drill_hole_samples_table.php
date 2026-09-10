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
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('sample_length', 8, 2)->nullable()->after('to_depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->dropColumn('sample_length');
        });
    }
};
