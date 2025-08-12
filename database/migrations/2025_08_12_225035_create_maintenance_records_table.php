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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->foreignId('technician_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('restrict');
            
            // Maintenance details
            $table->enum('maintenance_type', [
                'preventive', 'corrective', 'emergency', 'predictive', 
                'condition_based', 'breakdown', 'overhaul', 'upgrade'
            ]);
            $table->enum('priority_level', [
                'low', 'medium', 'high', 'critical', 'emergency'
            ])->default('medium');
            $table->enum('status', [
                'scheduled', 'pending_approval', 'approved', 'in_progress', 
                'on_hold', 'completed', 'cancelled', 'rejected'
            ])->default('scheduled');
            
            // Basic information
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('scheduled_date');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            
            // Duration and timing
            $table->integer('estimated_duration')->nullable()->comment('Duration in minutes');
            $table->integer('actual_duration')->nullable()->comment('Duration in minutes');
            
            // Cost tracking
            $table->decimal('labor_hours', 8, 2)->nullable();
            $table->decimal('labor_cost', 10, 2)->nullable();
            $table->decimal('parts_cost', 10, 2)->nullable();
            $table->decimal('external_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            
            // Equipment metrics
            $table->decimal('operating_hours_before', 10, 2)->nullable();
            $table->decimal('operating_hours_after', 10, 2)->nullable();
            
            // Work details
            $table->text('work_performed')->nullable();
            $table->json('parts_replaced')->nullable();
            $table->decimal('next_service_hours', 10, 2)->nullable();
            $table->datetime('next_service_date')->nullable();
            
            // Warranty and quality
            $table->datetime('warranty_expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('root_cause_analysis')->nullable();
            $table->json('preventive_actions')->nullable();
            
            // Administrative
            $table->string('work_order_number')->nullable()->unique();
            $table->string('external_vendor')->nullable();
            $table->string('invoice_number')->nullable();
            
            // Approval workflow
            $table->boolean('approval_required')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            
            // Quality and safety
            $table->boolean('is_warranty_work')->default(false);
            $table->json('safety_notes')->nullable();
            $table->boolean('quality_check_passed')->nullable();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['equipment_id', 'status']);
            $table->index(['technician_id', 'scheduled_date']);
            $table->index(['maintenance_type', 'priority_level']);
            $table->index(['scheduled_date', 'status']);
            $table->index('work_order_number');
            $table->index(['approval_required', 'approved_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};