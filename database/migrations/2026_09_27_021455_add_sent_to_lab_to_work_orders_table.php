<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->boolean('sent_to_lab')->default(false)->after('dispatch_date');
        });

        // Data migration: marcar como enviadas las WOs que ya tienen muestras archivadas
        DB::statement("
            UPDATE work_orders
            SET sent_to_lab = 1
            WHERE id IN (
                SELECT DISTINCT work_order_id
                FROM drill_hole_samples
                WHERE is_archived = 1 AND work_order_id IS NOT NULL
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('sent_to_lab');
        });
    }
};
