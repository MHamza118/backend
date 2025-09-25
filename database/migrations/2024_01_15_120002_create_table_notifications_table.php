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
        Schema::create('table_notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['new_order', 'order_updated', 'order_ready', 'order_delivered', 'table_changed']);
            $table->string('title');
            $table->text('message');
            $table->string('order_number');
            $table->string('table_number', 10)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('location')->nullable();
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->enum('recipient_type', ['admin', 'employee']);
            $table->unsignedBigInteger('recipient_id')->nullable(); // null = all admins/employees
            $table->json('data')->nullable(); // Additional data
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('order_number')->references('order_number')->on('table_orders')->onDelete('cascade');

            // Indexes
            $table->index('order_number');
            $table->index('type');
            $table->index('recipient_type');
            $table->index('recipient_id');
            $table->index('is_read');
            $table->index('priority');
            $table->index('created_at');

            // Composite indexes for common queries
            $table->index(['recipient_type', 'is_read']);
            $table->index(['recipient_type', 'recipient_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_notifications');
    }
};
