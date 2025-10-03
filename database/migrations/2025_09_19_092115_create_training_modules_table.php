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
        Schema::create('training_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description');
            $table->string('qr_code')->unique();
            $table->string('video_url')->nullable();
            $table->longText('content');
            $table->string('duration')->nullable(); // e.g., "30 minutes", "1 hour"
            $table->string('category');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('admins')->onDelete('set null');
            
            // Indexes
            $table->index(['category']);
            $table->index(['active']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_modules');
    }
};
