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
        if (Schema::hasTable('work_orders') && !Schema::hasColumn('work_orders', 'coreshack')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->string('coreshack')->nullable()->after('comments');
            });
        }

        if (Schema::hasTable('drill_hole_samples') && !Schema::hasColumn('drill_hole_samples', 'coreshack')) {
            Schema::table('drill_hole_samples', function (Blueprint $table) {
                $table->string('coreshack')->nullable()->after('comentarios');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'coreshack')) {
            Schema::table('work_orders', function (Blueprint $table) {
                $table->dropColumn('coreshack');
            });
        }

        if (Schema::hasTable('drill_hole_samples') && Schema::hasColumn('drill_hole_samples', 'coreshack')) {
            Schema::table('drill_hole_samples', function (Blueprint $table) {
                $table->dropColumn('coreshack');
            });
        }
    }
};
