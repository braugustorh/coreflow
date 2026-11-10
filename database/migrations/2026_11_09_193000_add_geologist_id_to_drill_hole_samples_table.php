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
            if (!Schema::hasColumn('drill_hole_samples', 'geologist_id')) {
                $table->foreignId('geologist_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            if (Schema::hasColumn('drill_hole_samples', 'geologist_id')) {
                $table->dropForeign(['geologist_id']);
                $table->dropColumn('geologist_id');
            }
        });
    }
};
