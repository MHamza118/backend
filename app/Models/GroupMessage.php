<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'sender_name',
        'content',
        'attachments',
        'has_attachments'
    ];

    protected $casts = [
        'attachments' => 'array',
        'has_attachments' => 'boolean',
    ];

    /**
     * Get the conversation that this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender (polymorphic relationship)
     */
    public function sender()
    {
        if ($this->sender_type === 'employee') {
            return $this->belongsTo(Employee::class, 'sender_id');
        }
        
        if ($this->sender_type === 'admin') {
            return $this->belongsTo(Admin::class, 'sender_id');
        }
        
        return null;
    }

    /**
     * Scope to get messages for a specific group conversation
     */
    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Scope to get recent messages (last N messages)
     */
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope to get messages from a specific sender
     */
    public function scopeFromSender($query, $senderId, $senderType)
    {
        return $query->where('sender_id', $senderId)
                    ->where('sender_type', $senderType);
    }

    /**
     * Scope to get messages with attachments
     */
    public function scopeWithAttachments($query)
    {
        return $query->where('has_attachments', true);
    }
}
