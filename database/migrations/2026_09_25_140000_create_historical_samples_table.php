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
        Schema::create('historical_samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_number', 100)->index();
            $table->foreignId('proyecto_id')
                ->nullable()
                ->constrained('proyectos')
                ->nullOnDelete();
            $table->string('barreno_name', 100)->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'sample_number'], 'hist_sample_project_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_samples');
    }
};
