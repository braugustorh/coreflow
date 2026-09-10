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
            $table->string('sampling_status')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->string('sampling_status')->default('Pending')->change();
        });
    }
};
