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
        Schema::create('qc_sample_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drill_hole_sample_id')
                ->constrained('drill_hole_samples')
                ->cascadeOnDelete();
            $table->string('type', 20); // 'scale' | 'context'
            $table->string('disk', 30)->default('coreflow');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['drill_hole_sample_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_sample_photos');
    }
};
