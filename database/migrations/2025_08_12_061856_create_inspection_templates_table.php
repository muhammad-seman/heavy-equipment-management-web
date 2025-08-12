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
        Schema::create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->onDelete('cascade');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('inspection_type', ['pre_operation', 'post_operation', 'daily', 'weekly', 'monthly', 'quarterly', 'annual', 'custom']);
            $table->integer('estimated_duration_minutes')->default(30);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('1.0');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index(['equipment_type_id']);
            $table->index(['inspection_type']);
            $table->index(['is_active']);
            $table->index(['is_mandatory']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_templates');
    }
};