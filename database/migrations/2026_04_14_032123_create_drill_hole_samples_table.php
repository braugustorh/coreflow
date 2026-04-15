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
        Schema::create('drill_hole_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // CSV columns / Fields
            $table->string('bhid')->nullable();
            $table->decimal('from', 10, 3)->nullable();
            $table->decimal('to', 10, 3)->nullable();
            $table->decimal('drilled_length', 10, 3)->nullable();
            $table->decimal('sample_length', 10, 3)->nullable();
            $table->string('sample_number')->nullable();
            $table->string('sample_type')->nullable();
            $table->string('control_type')->nullable();
            $table->decimal('wght', 10, 3)->nullable();
            $table->text('comments')->nullable();
            
            // Added columns
            $table->string('project')->nullable();
            $table->string('core_size')->nullable();
            $table->string('work_order')->nullable();
            $table->string('costal')->nullable();
            
            // State
            $table->string('status')->default('draft');
            $table->json('errors')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drill_hole_samples');
    }
};
