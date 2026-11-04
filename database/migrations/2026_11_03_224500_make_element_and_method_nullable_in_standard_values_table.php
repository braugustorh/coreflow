<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elemento y Método pasan a ser opcionales en los valores esperados de un estándar.
     */
    public function up(): void
    {
        Schema::table('standard_values', function (Blueprint $table) {
            $table->unsignedBigInteger('element_id')->nullable()->change();
            $table->unsignedBigInteger('assay_method_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('standard_values', function (Blueprint $table) {
            $table->unsignedBigInteger('element_id')->nullable(false)->change();
            $table->unsignedBigInteger('assay_method_id')->nullable(false)->change();
        });
    }
};
