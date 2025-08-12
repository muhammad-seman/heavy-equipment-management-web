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
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->foreignId('inspector_id')->constrained('users')->onDelete('restrict');
            
            // Inspection details
            $table->enum('inspection_type', [
                'scheduled', 'unscheduled', 'pre_operation', 'post_operation',
                'daily', 'weekly', 'monthly', 'annual'
            ]);
            $table->datetime('inspection_date')->nullable();
            $table->datetime('scheduled_date');
            $table->enum('status', [
                'scheduled', 'in_progress', 'completed', 'overdue', 'cancelled'
            ])->default('scheduled');
            $table->enum('overall_result', [
                'pass', 'fail', 'warning', 'pending'
            ])->default('pending');
            
            // Inspection content
            $table->text('notes')->nullable();
            $table->json('signature_data')->nullable();
            $table->datetime('completion_time')->nullable();
            $table->json('weather_conditions')->nullable();
            
            // Equipment metrics during inspection
            $table->decimal('operating_hours_before', 10, 2)->nullable();
            $table->decimal('operating_hours_after', 10, 2)->nullable();
            $table->integer('fuel_level_before')->nullable()->comment('Percentage 0-100');
            $table->integer('fuel_level_after')->nullable()->comment('Percentage 0-100');
            
            // Environmental conditions
            $table->string('location')->nullable();
            $table->decimal('temperature', 5, 1)->nullable()->comment('Celsius');
            $table->integer('humidity')->nullable()->comment('Percentage 0-100');
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['equipment_id', 'status']);
            $table->index(['inspector_id', 'inspection_date']);
            $table->index(['scheduled_date', 'status']);
            $table->index('inspection_type');
            $table->index('overall_result');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};