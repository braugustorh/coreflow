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
        Schema::table('sample_settings', function (Blueprint $table) {
            $table->boolean('require_qc_photos')->default(true)->after('duplicate_ratio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sample_settings', function (Blueprint $table) {
            $table->dropColumn('require_qc_photos');
        });
    }
};
