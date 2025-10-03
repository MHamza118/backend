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
        Schema::create('training_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('module_id', 36); // UUID as char(36)
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('assigned_at');
            $table->timestamp('due_date')->nullable();
            $table->enum('status', ['assigned', 'unlocked', 'in_progress', 'completed', 'overdue', 'removed'])->default('assigned');
            $table->text('notes')->nullable();
            
            // Progress tracking
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('completion_data')->nullable();
            
            // Reset tracking
            $table->integer('reset_count')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('module_id')->references('id')->on('training_modules')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('admins')->onDelete('cascade');
            
            // Indexes
            $table->index(['module_id']);
            $table->index(['employee_id']);
            $table->index(['status']);
            $table->index(['assigned_at']);
            $table->index(['due_date']);
            
            // Unique constraint to prevent duplicate assignments
            $table->unique(['module_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_assignments');
    }
};
