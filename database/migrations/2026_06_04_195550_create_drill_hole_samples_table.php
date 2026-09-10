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
        Schema::dropIfExists('drill_hole_samples'); // Just in case, drop the old one since user authorized drop & create
        Schema::create('drill_hole_samples', function (Blueprint $table) {
            $table->id();
            
            // Relaciones principales
            $table->foreignId('barreno_id')->constrained('drill_holes')->onDelete('cascade');
            $table->foreignId('proyecto_id')->constrained('proyectos')->onDelete('cascade');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            
            // Auditoría
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Atributos Core
            $table->string('sample_number')->nullable(); // Unique por proyecto (se puede manejar en código o índice compuesto si no hay nulos, pero lo dejaremos nullable por si acaso temporalmente)
            $table->string('qr_token')->unique()->index()->nullable();
            
            // Profundidades
            $table->decimal('from_depth', 8, 2);
            $table->decimal('to_depth', 8, 2);
            $table->decimal('length', 8, 2)->nullable(); // calculado: to_depth - from_depth
            
            // QA/QC
            $table->enum('sample_type', ['O', 'Control'])->default('O');
            $table->enum('control_type', ['Estándar', 'Blanco', 'Duplicado'])->nullable();
            
            $table->foreignId('standard_sample_id')->nullable()->constrained('standard_samples')->nullOnDelete();
            $table->foreignId('duplicate_sample_id')->nullable()->constrained('drill_hole_samples')->nullOnDelete();
            
            // Detalles de Muestra
            $table->decimal('weight', 8, 2)->nullable();
            $table->enum('core_size', ['PQ', 'HQ', 'NQ', 'BQ'])->nullable();
            $table->text('comentarios')->nullable();
            $table->timestamp('sampled_at')->nullable();
            
            // Origen de los Datos
            $table->enum('capture_source', ['manual', 'import'])->default('manual');
            
            // Campos Logísticos (Fase 2)
            $table->string('hole_status')->nullable();
            $table->date('sent_date')->nullable();
            $table->string('sampling_assistant')->nullable();
            $table->integer('bags')->nullable();
            $table->integer('sacks')->nullable();
            $table->string('responsable_supervisor')->nullable();
            $table->string('sampling_status')->default('PENDING');
            $table->boolean('rush')->default(false);
            
            // Estado y errores de la fase actual/importación (opcional si queremos mantener retrocompatibilidad de UI)
            $table->string('status')->default('draft');
            $table->json('errors')->nullable();
            
            $table->timestamps();
            
            // Índices adicionales útiles
            $table->unique(['proyecto_id', 'sample_number'], 'unique_sample_number_per_project');
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
