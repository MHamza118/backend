<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_off_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type'); // vacation, sick, personal, emergency, other
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('approved_by')->nullable(); // admin id
            $table->text('decision_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_off_requests');
    }
};
