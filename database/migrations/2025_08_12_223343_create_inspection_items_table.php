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
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('inspection_id')->constrained('inspections')->onDelete('cascade');
            $table->foreignId('inspection_template_item_id')->nullable()
                  ->constrained('inspection_template_items')->onDelete('set null');
            
            // Item details (copied from template or custom)
            $table->string('item_name');
            $table->text('item_description')->nullable();
            $table->enum('category', [
                'engine', 'hydraulic', 'electrical', 'structural', 'safety',
                'operational', 'maintenance', 'fluids', 'attachments', 'documentation'
            ]);
            $table->enum('item_type', [
                'visual', 'measurement', 'functional', 'checklist', 'photo',
                'signature', 'text', 'numeric', 'boolean'
            ]);
            
            // Validation settings
            $table->boolean('is_required')->default(false);
            $table->integer('order_sequence')->default(1);
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->string('expected_condition')->nullable();
            $table->boolean('safety_critical')->default(false);
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['inspection_id', 'order_sequence']);
            $table->index(['category', 'item_type']);
            $table->index(['safety_critical', 'is_required']);
            $table->index('inspection_template_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};