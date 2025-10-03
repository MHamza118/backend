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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['info', 'warning', 'success', 'error', 'event']);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent']);
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dismissible')->default(true);
            $table->string('action_text')->nullable();
            $table->string('action_url')->nullable();
            $table->enum('target_audience', ['all', 'loyalty_tier', 'location', 'custom']);
            $table->json('target_criteria')->nullable();
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'start_date', 'end_date']);
            $table->index('type');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
