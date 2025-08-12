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
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('equipment_categories')->onDelete('restrict');
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('operating_weight_min', 10, 2)->nullable();
            $table->decimal('operating_weight_max', 10, 2)->nullable();
            $table->decimal('engine_power_min', 8, 2)->nullable();
            $table->decimal('engine_power_max', 8, 2)->nullable();
            $table->decimal('bucket_capacity_min', 8, 2)->nullable();
            $table->decimal('bucket_capacity_max', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['category_id']);
            $table->index(['code']);
            $table->index(['name']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
    }
};