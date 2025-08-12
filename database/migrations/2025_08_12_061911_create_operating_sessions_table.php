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
        Schema::create('operating_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->foreignId('operator_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('shift_supervisor_id')->nullable()->constrained('users')->onDelete('set null');

            // Session timing
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('planned_duration_hours', 6, 2)->nullable();
            $table->decimal('actual_duration_hours', 6, 2)->nullable();

            // Location and project
            $table->string('work_site', 255)->nullable();
            $table->string('project_code', 100)->nullable();
            $table->string('work_area', 255)->nullable();
            $table->decimal('start_location_lat', 10, 8)->nullable();
            $table->decimal('start_location_lng', 11, 8)->nullable();
            $table->decimal('end_location_lat', 10, 8)->nullable();
            $table->decimal('end_location_lng', 11, 8)->nullable();

            // Equipment readings
            $table->decimal('start_hours', 10, 1);
            $table->decimal('end_hours', 10, 1)->nullable();
            $table->decimal('start_odometer', 12, 2)->nullable();
            $table->decimal('end_odometer', 12, 2)->nullable();
            $table->decimal('fuel_level_start', 5, 2)->nullable();
            $table->decimal('fuel_level_end', 5, 2)->nullable();

            // Performance metrics
            $table->decimal('material_moved_cubic_meters', 12, 2)->nullable();
            $table->integer('loads_completed')->nullable();
            $table->decimal('distance_traveled_km', 8, 2)->nullable();
            $table->decimal('fuel_consumed_liters', 8, 2)->nullable();

            // Conditions
            $table->string('weather_conditions', 100)->nullable();
            $table->string('terrain_type', 100)->nullable();
            $table->string('work_type', 100)->nullable();

            // Issues and notes
            $table->text('issues_reported')->nullable();
            $table->text('operator_notes')->nullable();
            $table->text('supervisor_notes')->nullable();

            // Status
            $table->enum('status', ['active', 'completed', 'interrupted', 'cancelled'])->default('active');
            $table->string('interruption_reason', 255)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['equipment_id']);
            $table->index(['operator_id']);
            $table->index(['started_at']);
            $table->index(['work_site']);
            $table->index(['status']);
            $table->index(['equipment_id', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_sessions');
    }
};