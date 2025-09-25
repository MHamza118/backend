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
        Schema::create('employee_onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_page_id')->constrained('onboarding_pages')->onDelete('cascade');
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->text('signature')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Unique constraint to prevent duplicate progress records
            $table->unique(['employee_id', 'onboarding_page_id'], 'emp_onboarding_progress_unique');
            
            // Index for queries
            $table->index(['employee_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_onboarding_progress');
    }
};
