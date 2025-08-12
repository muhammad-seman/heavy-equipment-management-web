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
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            
            // Core relationships
            $table->foreignId('inspection_id')->constrained('inspections')->onDelete('cascade');
            $table->foreignId('inspection_item_id')->constrained('inspection_items')->onDelete('cascade');
            
            // Result data
            $table->json('result_value')->nullable()->comment('Flexible storage for different value types');
            $table->enum('result_status', [
                'pass', 'fail', 'warning', 'not_applicable', 'pending', 'requires_recheck'
            ])->default('pending');
            $table->text('result_notes')->nullable();
            $table->decimal('measured_value', 10, 2)->nullable();
            
            // Media attachments
            $table->string('photo_path')->nullable();
            $table->json('signature_data')->nullable();
            
            // Analysis and validation
            $table->boolean('is_within_tolerance')->nullable();
            $table->decimal('deviation_percentage', 5, 2)->nullable();
            
            // Action requirements
            $table->boolean('requires_action')->default(false);
            $table->enum('action_required', [
                'none', 'monitor', 'repair', 'replace', 'adjust', 'clean', 
                'lubricate', 'tighten', 'investigate', 'shutdown'
            ])->nullable();
            $table->enum('priority_level', [
                'low', 'medium', 'high', 'critical'
            ])->default('low');
            
            // Inspector notes and timestamp
            $table->text('inspector_notes')->nullable();
            $table->datetime('timestamp_checked')->nullable();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['inspection_id', 'result_status']);
            $table->index(['inspection_item_id', 'result_status']);
            $table->index(['requires_action', 'priority_level']);
            $table->index('timestamp_checked');
            $table->index(['result_status', 'priority_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
