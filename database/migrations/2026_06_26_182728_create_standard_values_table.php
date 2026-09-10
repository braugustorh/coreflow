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
        Schema::create('standard_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_sample_id')->constrained('standard_samples')->cascadeOnDelete();
            $table->foreignId('element_id')->constrained('elements');
            $table->foreignId('assay_method_id')->constrained('assay_methods');
            $table->decimal('mean', 12, 4)->nullable();
            $table->decimal('min', 12, 4)->nullable();
            $table->decimal('max', 12, 4)->nullable();
            $table->decimal('std_dev', 12, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standard_values');
    }
};
