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
        Schema::table('sample_settings', function (Blueprint $table) {
            $table->decimal('factor_pq', 8, 2)->default(7.66)->after('max_sample_weight');
            $table->decimal('factor_hq', 8, 2)->default(4.28)->after('factor_pq');
            $table->decimal('factor_nq', 8, 2)->default(2.40)->after('factor_hq');
            $table->decimal('factor_bq', 8, 2)->default(1.40)->after('factor_nq');
            $table->decimal('blank_weight', 8, 2)->default(3.00)->after('factor_bq');
            $table->decimal('standard_weight', 8, 3)->default(0.065)->after('blank_weight');
            $table->decimal('duplicate_ratio', 5, 2)->default(50.00)->after('standard_weight');
        });

        // Actualizar registros existentes si los hay
        DB::table('sample_settings')->update([
            'factor_pq'        => 7.66,
            'factor_hq'        => 4.28,
            'factor_nq'        => 2.40,
            'factor_bq'        => 1.40,
            'blank_weight'     => 3.00,
            'standard_weight'  => 0.065,
            'duplicate_ratio'  => 50.00,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sample_settings', function (Blueprint $table) {
            $table->dropColumn([
                'factor_pq',
                'factor_hq',
                'factor_nq',
                'factor_bq',
                'blank_weight',
                'standard_weight',
                'duplicate_ratio',
            ]);
        });
    }
};
