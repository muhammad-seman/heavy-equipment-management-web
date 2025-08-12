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
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->onDelete('cascade');
            $table->foreignId('equipment_type_id')->nullable()->constrained('equipment_types')->onDelete('cascade');
            
            // Schedule details
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('maintenance_type', [
                'preventive', 'corrective', 'emergency', 'predictive', 
                'condition_based', 'breakdown', 'overhaul', 'upgrade'
            ])->default('preventive');
            $table->enum('priority_level', [
                'low', 'medium', 'high', 'critical', 'emergency'
            ])->default('medium');
            
            // Schedule configuration
            $table->enum('schedule_type', [
                'time_based', 'hour_based', 'mileage_based', 'cycle_based', 
                'condition_based', 'calendar_based'
            ]);
            
            // Interval settings
            $table->decimal('interval_hours', 10, 2)->nullable();
            $table->integer('interval_days')->nullable();
            $table->decimal('interval_kilometers', 10, 2)->nullable();
            $table->integer('interval_cycles')->nullable();
            
            // Tolerance settings
            $table->decimal('tolerance_hours', 8, 2)->nullable();
            $table->integer('tolerance_days')->nullable();
            
            // Estimation
            $table->integer('estimated_duration_minutes')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            
            // Requirements
            $table->json('required_skills')->nullable();
            $table->json('required_tools')->nullable();
            $table->json('required_parts')->nullable();
            $table->json('safety_requirements')->nullable();
            $table->json('work_instructions')->nullable();
            
            // Last performed tracking
            $table->datetime('last_performed_at')->nullable();
            $table->decimal('last_performed_hours', 10, 2)->nullable();
            $table->decimal('last_performed_kilometers', 10, 2)->nullable();
            
            // Next due tracking
            $table->datetime('next_due_date')->nullable();
            $table->decimal('next_due_hours', 10, 2)->nullable();
            $table->decimal('next_due_kilometers', 10, 2)->nullable();
            
            // Schedule settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_generate')->default(true);
            $table->integer('advance_notice_days')->default(7);
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['equipment_id', 'is_active']);
            $table->index(['equipment_type_id', 'is_active']);
            $table->index(['schedule_type', 'maintenance_type']);
            $table->index(['next_due_date', 'is_active']);
            $table->index(['next_due_hours', 'is_active']);
            $table->index(['auto_generate', 'is_active']);
            $table->index('priority_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};