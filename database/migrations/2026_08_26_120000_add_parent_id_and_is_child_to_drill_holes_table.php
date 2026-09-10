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
            $table->boolean('is_child')->default(false)->after('nombre_barreno');
            $table->foreignId('parent_id')->nullable()->after('is_child')->constrained('drill_holes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drill_holes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['is_child', 'parent_id']);
        });
    }
};
