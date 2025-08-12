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
        Schema::table('users', function (Blueprint $table) {
            // Add first_name and last_name only if they don't exist
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->after('id');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->after('first_name');
            }
            
            // Additional user fields - check if they exist first
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->string('employee_id', 50)->unique()->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department', 100)->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('users', 'position')) {
                $table->string('position', 100)->nullable()->after('department');
            }
            if (!Schema::hasColumn('users', 'certification_level')) {
                $table->enum('certification_level', ['basic', 'intermediate', 'advanced', 'expert'])->default('basic')->after('position');
            }
            if (!Schema::hasColumn('users', 'certification_expiry')) {
                $table->date('certification_expiry')->nullable()->after('certification_level');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('certification_expiry');
            }
            if (!Schema::hasColumn('users', 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('is_active');
            }
            
            // Add soft deletes if not exists
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        
        // Add indexes separately
        Schema::table('users', function (Blueprint $table) {
            // Only add indexes if they don't exist already
            try {
                $table->index(['email']);
            } catch (\Exception $e) {
                // Index already exists
            }
            
            try {
                $table->index(['employee_id']);
            } catch (\Exception $e) {
                // Index already exists
            }
            
            try {
                $table->index(['certification_level']);
            } catch (\Exception $e) {
                // Index already exists
            }
            
            try {
                $table->index(['is_active']);
            } catch (\Exception $e) {
                // Index already exists
            }
            
            try {
                $table->index(['deleted_at']);
            } catch (\Exception $e) {
                // Index already exists
            }
        });

        // Drop the name column if it exists (will be replaced by first_name + last_name)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'employee_id',
                'department',
                'position',
                'certification_level',
                'certification_expiry',
                'is_active',
                'last_login'
            ]);
            
            // Add back name column
            $table->string('name')->after('id');
            
            // Remove soft deletes
            $table->dropSoftDeletes();
            
            // Drop indexes (Laravel will handle this automatically when dropping columns)
        });
    }
};