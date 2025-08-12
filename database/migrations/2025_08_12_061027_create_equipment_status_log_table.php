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
        Schema::create('equipment_status_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->onDelete('cascade');
            $table->enum('previous_status', ['active', 'maintenance', 'repair', 'standby', 'retired', 'disposal'])->nullable();
            $table->enum('new_status', ['active', 'maintenance', 'repair', 'standby', 'retired', 'disposal']);
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('changed_at')->default(now());
            $table->timestamps();

            // Indexes
            $table->index(['equipment_id']);
            $table->index(['changed_at']);
            $table->index(['new_status']);
            $table->index(['changed_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_status_log');
    }
};