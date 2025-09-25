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
        // Update the enum constraint to include the new roles
        \DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('owner', 'admin', 'manager', 'hiring_manager', 'expo') NOT NULL DEFAULT 'admin'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        \DB::statement("ALTER TABLE admins MODIFY COLUMN role ENUM('owner', 'admin', 'manager') NOT NULL DEFAULT 'admin'");
    }
};
