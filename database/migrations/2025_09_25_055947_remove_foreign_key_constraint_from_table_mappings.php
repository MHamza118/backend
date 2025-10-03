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
        Schema::table('table_mappings', function (Blueprint $table) {
            // Drop the foreign key constraint that prevents multiple orders with same order_number
            $table->dropForeign(['order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_mappings', function (Blueprint $table) {
            // Add the foreign key constraint back
            $table->foreign('order_number')->references('order_number')->on('table_orders')->onDelete('cascade');
        });
    }
};
