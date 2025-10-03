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
        Schema::table('table_orders', function (Blueprint $table) {
            // Drop the unique constraint on order_number
            $table->dropUnique(['order_number']);
            
            // Add new fields for proper tracking
            $table->string('unique_identifier')->nullable()->after('order_number');
            $table->unsignedBigInteger('mapping_id')->nullable()->after('unique_identifier');
            $table->string('table_number')->nullable()->after('mapping_id');
            $table->string('area')->nullable()->after('table_number');
            
            // Add indexes for better performance
            $table->index(['order_number', 'table_number'], 'order_table_index');
            $table->index('unique_identifier');
            $table->index('mapping_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_orders', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['unique_identifier', 'mapping_id', 'table_number', 'area']);
            
            // Drop indexes
            $table->dropIndex('order_table_index');
            $table->dropIndex(['unique_identifier']);
            $table->dropIndex(['mapping_id']);
            
            // Restore unique constraint (this might fail if there are duplicate order numbers)
            $table->unique('order_number');
        });
    }
};
