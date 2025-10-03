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
        Schema::table('admins', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('role');
            $table->string('department')->nullable()->after('location_id');
            $table->text('notes')->nullable()->after('department');
            
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->index('location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn(['location_id', 'department', 'notes']);
        });
    }
};
