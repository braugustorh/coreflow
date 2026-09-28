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
            $table->boolean('is_historical')->default(false)->after('purpose')->index();
            $table->string('status', 50)->default('active')->after('is_historical')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->dropIndex(['is_historical']);
            $table->dropIndex(['status']);
            $table->dropColumn(['is_historical', 'status']);
        });
    }
};
