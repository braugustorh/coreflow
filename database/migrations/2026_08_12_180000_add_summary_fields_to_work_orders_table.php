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
        Schema::table('work_orders', function (Blueprint $table) {
            $table->date('dispatch_date')->nullable()->after('samples_quantity');
            $table->integer('received_samples_count')->nullable()->after('dispatch_date');
            $table->date('reception_date')->nullable()->after('received_samples_count');
            $table->string('status')->nullable()->after('reception_date');
            $table->text('comments')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn([
                'dispatch_date',
                'received_samples_count',
                'reception_date',
                'status',
                'comments',
            ]);
        });
    }
};
