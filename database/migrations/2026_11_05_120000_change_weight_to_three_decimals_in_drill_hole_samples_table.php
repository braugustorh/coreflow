<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('weight', 8, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('drill_hole_samples', function (Blueprint $table) {
            $table->decimal('weight', 8, 2)->nullable()->change();
        });
    }
};
