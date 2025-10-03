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
        Schema::table('employees', function (Blueprint $table) {
            // Update the enum to include new lifecycle statuses
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'paused', 'inactive'])
                  ->default('pending_approval')
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])
                  ->default('pending_approval')
                  ->change();
        });
    }
};
