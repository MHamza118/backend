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
            // Drop the unique constraint that prevents multiple active mappings per order
            $table->dropUnique('unique_active_mapping');
            
            // Add new fields for better tracking
            $table->string('submission_id')->nullable()->after('order_number');
            $table->timestamp('submitted_at')->nullable()->after('submission_id');
            $table->integer('update_count')->default(0)->after('notes');
            
            // Add composite index for better performance
            $table->index(['order_number', 'table_number', 'created_at'], 'order_table_time_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('table_mappings', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['submission_id', 'submitted_at', 'update_count']);
            
            // Drop the composite index
            $table->dropIndex('order_table_time_index');
            
            // Restore the unique constraint (this might fail if there are already duplicate active mappings)
            $table->unique(['order_number', 'status'], 'unique_active_mapping');
        });
    }
};
