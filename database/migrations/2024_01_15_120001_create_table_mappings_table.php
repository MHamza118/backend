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
        Schema::create('table_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->string('table_number', 10);
            $table->enum('area', ['dining', 'patio', 'bar'])->default('dining');
            $table->enum('status', ['active', 'delivered', 'cleared'])->default('active');
            $table->enum('source', ['customer', 'admin'])->default('customer');
            $table->string('delivered_by')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->string('clear_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('order_number')->references('order_number')->on('table_orders')->onDelete('cascade');

            // Indexes
            $table->index('order_number');
            $table->index('table_number');
            $table->index('status');
            $table->index('area');
            $table->index('created_at');
            $table->index('delivered_at');

            // Unique constraint - one active mapping per order
            $table->unique(['order_number', 'status'], 'unique_active_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_mappings');
    }
};
