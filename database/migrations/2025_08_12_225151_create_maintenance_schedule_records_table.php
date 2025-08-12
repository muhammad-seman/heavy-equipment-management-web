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
        Schema::create('maintenance_schedule_records', function (Blueprint $table) {
            $table->id();
            
            // Pivot relationship
            $table->foreignId('maintenance_schedule_id')->constrained('maintenance_schedules')->onDelete('cascade');
            $table->foreignId('maintenance_record_id')->constrained('maintenance_records')->onDelete('cascade');
            
            // Additional pivot data
            $table->boolean('schedule_fulfilled')->default(false);
            $table->text('fulfillment_notes')->nullable();
            $table->datetime('scheduled_for')->nullable();
            $table->datetime('completed_at')->nullable();
            
            $table->timestamps();
            
            // Indexes and constraints
            $table->unique(['maintenance_schedule_id', 'maintenance_record_id'], 'schedule_record_unique');
            $table->index(['maintenance_schedule_id', 'schedule_fulfilled'], 'schedule_fulfilled_idx');
            $table->index(['maintenance_record_id', 'completed_at'], 'record_completed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedule_records');
    }
};