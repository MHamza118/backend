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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('category', [
                'broken-equipment',
                'software-issue', 
                'pos-problem',
                'kitchen-equipment',
                'facility-issue',
                'other'
            ])->default('other');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in-progress', 'resolved', 'closed'])->default('open');
            $table->string('location')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index(['employee_id', 'status']);
            $table->index(['category', 'priority']);
            $table->index(['archived', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
