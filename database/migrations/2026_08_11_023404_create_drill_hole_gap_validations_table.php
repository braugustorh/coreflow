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
        Schema::create('drill_hole_gap_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barreno_id')->constrained('drill_holes')->cascadeOnDelete();
            $table->decimal('from_depth', 8, 2);
            $table->decimal('to_depth', 8, 2);
            $table->string('reason');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drill_hole_gap_validations');
    }
};
