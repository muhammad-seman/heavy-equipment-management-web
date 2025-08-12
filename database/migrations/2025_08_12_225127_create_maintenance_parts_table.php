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
        Schema::create('maintenance_parts', function (Blueprint $table) {
            $table->id();
            
            // Core relationship
            $table->foreignId('maintenance_record_id')->constrained('maintenance_records')->onDelete('cascade');
            
            // Part identification
            $table->string('part_number');
            $table->string('part_name');
            $table->text('part_description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->enum('category', [
                'engine', 'hydraulic', 'electrical', 'transmission', 'cooling', 'fuel',
                'brake', 'track', 'attachment', 'cabin', 'filter', 'bearing', 
                'seal', 'fastener', 'lubricant', 'consumable'
            ]);
            
            // Quantity and cost
            $table->decimal('quantity_used', 8, 2);
            $table->string('unit_of_measure')->default('pcs');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            
            // Procurement information
            $table->string('supplier')->nullable();
            $table->string('purchase_order_number')->nullable();
            
            // Warranty and installation
            $table->integer('warranty_period_months')->nullable();
            $table->datetime('warranty_expires_at')->nullable();
            $table->datetime('installation_date')->nullable();
            
            // Part specifications
            $table->enum('part_condition', [
                'new', 'refurbished', 'used', 'core_exchange'
            ])->default('new');
            $table->enum('part_source', [
                'oem', 'aftermarket', 'internal_stock', 'emergency_purchase', 'warranty_replacement'
            ])->default('oem');
            
            // Old part management
            $table->enum('old_part_condition', [
                'serviceable', 'repairable', 'scrap', 'core_return', 'disposed'
            ])->nullable();
            $table->boolean('old_part_disposed')->default(false);
            
            // Installation and notes
            $table->text('installation_notes')->nullable();
            $table->boolean('is_critical_part')->default(false);
            
            // Inventory management
            $table->integer('lead_time_days')->nullable();
            $table->integer('minimum_stock_level')->nullable();
            $table->integer('current_stock_level')->nullable();
            $table->integer('reorder_point')->nullable();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['maintenance_record_id', 'category']);
            $table->index('part_number');
            $table->index(['supplier', 'part_number']);
            $table->index(['warranty_expires_at', 'part_condition']);
            $table->index(['is_critical_part', 'current_stock_level']);
            $table->index('purchase_order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_parts');
    }
};