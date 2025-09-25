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
        Schema::create('training_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('assignment_id'); // Links to training_assignments.id (UUID)
            $table->unsignedBigInteger('employee_id'); // Links to employees.id
            $table->string('module_id'); // Links to training_modules.id (UUID)
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->integer('time_spent_minutes')->default(0);
            $table->json('progress_data')->nullable(); // Store detailed progress info
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('assignment_id')->references('id')->on('training_assignments')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('training_modules')->onDelete('cascade');
            
            // Indexes for better performance
            $table->index(['assignment_id']);
            $table->index(['employee_id']);
            $table->index(['module_id']);
            $table->index(['session_start']);
            $table->index(['employee_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_progress');
    }
};
