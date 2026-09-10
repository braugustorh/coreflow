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
            $table->decimal('from_depth', 8, 2)->nullable()->change();
            $table->decimal('to_depth', 8, 2)->nullable()->change();
            $table->decimal('length', 8, 2)->nullable()->change();
            $table->decimal('sample_length', 8, 2)->nullable()->change();
            $table->decimal('weight', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('from_depth', 8, 2)->nullable(false)->change();
            $table->decimal('to_depth', 8, 2)->nullable(false)->change();
            $table->decimal('length', 8, 2)->nullable(false)->change();
            $table->decimal('sample_length', 8, 2)->nullable(false)->change();
            $table->decimal('weight', 8, 2)->nullable(false)->change();
        });
    }
};
