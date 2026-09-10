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
        Schema::create('sample_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('max_sack_weight', 8, 2)->default(25.00);
            $table->decimal('min_sample_weight', 8, 2)->default(0.50);
            $table->decimal('max_sample_weight', 8, 2)->default(15.00);
            $table->timestamps();
        });

        // Insertar registro único por defecto
        DB::table('sample_settings')->insert([
            'max_sack_weight'   => 25.00,
            'min_sample_weight' => 0.50,
            'max_sample_weight' => 15.00,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sample_settings');
    }
};
