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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->onDelete('restrict');
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->onDelete('restrict');
            
            // Basic information
            $table->string('asset_number', 50)->unique();
            $table->string('serial_number', 100)->unique();
            $table->string('model', 100);
            $table->year('year_manufactured');
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expiry')->nullable();
            
            // Technical specifications
            $table->string('engine_model', 100)->nullable();
            $table->string('engine_serial', 100)->nullable();
            $table->decimal('operating_weight', 10, 2)->nullable();
            $table->decimal('engine_power', 8, 2)->nullable();
            $table->decimal('bucket_capacity', 8, 2)->nullable();
            $table->decimal('max_digging_depth', 8, 2)->nullable();
            $table->decimal('max_reach', 8, 2)->nullable();
            $table->decimal('travel_speed', 6, 2)->nullable();
            $table->decimal('fuel_capacity', 8, 2)->nullable();
            
            // Operational data
            $table->decimal('total_operating_hours', 10, 1)->default(0);
            $table->decimal('total_distance_km', 12, 2)->default(0);
            $table->decimal('last_service_hours', 10, 1)->default(0);
            $table->decimal('next_service_hours', 10, 1)->nullable();
            
            // Status and location
            $table->enum('status', ['active', 'maintenance', 'repair', 'standby', 'retired', 'disposal'])->default('active');
            $table->timestamp('status_changed_at')->default(now());
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('status_notes')->nullable();
            
            // Ownership and assignment
            $table->enum('ownership_type', ['owned', 'leased', 'rented'])->default('owned');
            $table->date('lease_start_date')->nullable();
            $table->date('lease_end_date')->nullable();
            $table->decimal('lease_cost_monthly', 12, 2)->nullable();
            $table->foreignId('assigned_to_user')->nullable()->constrained('users')->onDelete('set null');
            $table->string('assigned_to_site', 100)->nullable();
            $table->decimal('current_location_lat', 10, 8)->nullable();
            $table->decimal('current_location_lng', 11, 8)->nullable();
            $table->text('current_location_address')->nullable();
            
            // Financial data
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('current_book_value', 15, 2)->nullable();
            $table->decimal('depreciation_rate', 5, 2)->nullable();
            $table->string('insurance_policy', 100)->nullable();
            $table->date('insurance_expiry')->nullable();
            
            // Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['asset_number']);
            $table->index(['serial_number']);
            $table->index(['equipment_type_id']);
            $table->index(['manufacturer_id']);
            $table->index(['status']);
            $table->index(['assigned_to_user']);
            $table->index(['current_location_lat', 'current_location_lng']);
            $table->index(['total_operating_hours']);
            $table->index(['next_service_hours']);
            $table->index(['status', 'equipment_type_id']);
            
            // Full-text search index
            $table->fullText(['model', 'asset_number', 'serial_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};