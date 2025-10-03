<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->string('sender_id'); // Can be employee ID or 'admin'
            $table->enum('sender_type', ['employee', 'admin']);
            $table->string('sender_name');
            $table->text('content');
            $table->json('attachments')->nullable(); // For file attachments
            $table->boolean('has_attachments')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes optimized for private chat queries
            $table->index(['conversation_id', 'created_at'], 'private_messages_conversation_time_idx');
            $table->index(['sender_id', 'sender_type'], 'private_messages_sender_idx');
            $table->index(['conversation_id', 'is_read'], 'private_messages_conversation_read_idx');
            $table->index('created_at', 'private_messages_created_at_idx');
            
            // Ensure only private conversations can have private messages
            $table->index('conversation_id', 'private_messages_conversation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_messages');
    }
};
