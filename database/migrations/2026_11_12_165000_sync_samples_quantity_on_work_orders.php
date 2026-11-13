<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reconcilia samples_quantity en work_orders con el conteo real en drill_hole_samples.
     */
    public function up(): void
    {
        DB::statement('
            UPDATE work_orders
            SET samples_quantity = (
                SELECT COUNT(*)
                FROM drill_hole_samples
                WHERE drill_hole_samples.work_order_id = work_orders.id
            )
        ');
    }

    public function down(): void
    {
        // Reconciliación de datos no reversible.
    }
};
